<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Command;

use Exception;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Clean extends Command {
	public const PRINT_LEVEL_SUCCESS = 1;
	public const PRINT_LEVEL_INFO = 2;
	public const PRINT_LEVEL_WARNING = 3;
	public const PRINT_LEVEL_ERROR = 4;

	protected ColumnService $columnService;
	protected RowService $rowService;
	protected TableService $tableService;
	protected LoggerInterface $logger;
	protected Row2Mapper $rowMapper;

	private bool $dry = false;
	private int $truncateLength = 20;

	private ?Row2 $row = null;
	private int $offset = -1;

	private OutputInterface $output;

	public function __construct(LoggerInterface $logger, ColumnService $columnService, RowService $rowService, TableService $tableService, Row2Mapper $rowMapper) {
		parent::__construct();
		$this->logger = $logger;
		$this->columnService = $columnService;
		$this->rowService = $rowService;
		$this->tableService = $tableService;
		$this->rowMapper = $rowMapper;
	}

	protected function configure(): void {
		$this
			->setName('tables:clean')
			->setDescription('Clean the tables data.')
			->addOption(
				'dry',
				'd',
				InputOption::VALUE_NONE,
				'Prints all wanted changes, but do not write anything to the database.'
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->output = $output;
		$this->dry = (bool)$input->getOption('dry');

		if ($this->dry) {
			$this->print('Dry run activated.');
		}
		if ($output->isVerbose()) {
			$this->print('Verbose mode activated.');
		}

		// check action, starting point for magic
		$this->checkIfColumnsForRowsExists();

		return 0;
	}

	private function getNextRow():void {
		try {
			$nextRowId = $this->rowMapper->findNextId($this->offset);
			if ($nextRowId === null) {
				$this->print('');
				$this->print('No more rows found.', self::PRINT_LEVEL_INFO);
				$this->print('');
				$this->row = null;
				return;
			}
			$tableId = $this->rowMapper->getTableIdForRow($nextRowId);
			$columns = $this->columnService->findAllByTable($tableId, '');
			$this->row = $this->rowMapper->find($nextRowId, $columns);
			$this->offset = $this->row->getId();
		} catch (Exception $e) {
			$this->print('Error while fetching row', self::PRINT_LEVEL_ERROR);
			$this->logger->error('Following error occurred during executing occ command "' . self::class . '"', ['exception' => $e]);
		}
	}


	/**
	 * Take each data set from all rows and check if the column (mapped by id) exists
	 *
	 * @return void
	 */
	private function checkIfColumnsForRowsExists(): void {

		$this->getNextRow();
		while ($this->row) {
			$this->print('');
			$this->print('');
			$this->print('Lets check row with id = ' . $this->row->getId());
			$this->print('==========================================');

			$this->checkColumns();

			$this->getNextRow();
		}
	}

	private function checkColumns(): void {
		foreach ($this->row->getData() as $date) {
			$valueAsString = is_string($date['value']) ? $date['value'] : json_encode($date['value']);
			$suffix = strlen($valueAsString) > $this->truncateLength ? '...': '';
			$this->print('');
			$this->print('columnId: ' . $date['columnId'] . ' -> ' . substr($valueAsString, 0, $this->truncateLength) . $suffix, self::PRINT_LEVEL_INFO);

			try {
				$this->columnService->find($date['columnId'], '');
				if ($this->output->isVerbose()) {
					$this->print('column found', self::PRINT_LEVEL_SUCCESS);
				}
			} catch (InternalError $e) {
				$this->print('😱️ internal error while looking for column', self::PRINT_LEVEL_ERROR);
				$this->logger->error('Following error occurred during executing occ command "' . self::class . '"', ['exception' => $e]);
			} catch (NotFoundError $e) {
				if ($this->output->isVerbose()) {
					$this->print('corresponding column not found.', self::PRINT_LEVEL_ERROR);
				} else {
					$this->print('columnId: ' . $date['columnId'] . ' not found, but needed by row ' . $this->row->getId(), self::PRINT_LEVEL_WARNING);
				}
				// if the corresponding column is not found, lets delete the data from the row.
				$this->deleteDataFromRow($date['columnId']);
			} catch (PermissionError $e) {
				$this->print('😱️ permission error while looking for column', self::PRINT_LEVEL_ERROR);
				$this->logger->error('Following error occurred during executing occ command "' . self::class . '"', ['exception' => $e]);
			}
		}
	}

	/**
	 * Deletes the data for a given columnID from the dataset within a row
	 * @param int $columnId
	 * @return void
	 */
	private function deleteDataFromRow(int $columnId): void {
		if ($this->dry) {
			$this->print('Is dry run, will not remove the column data from row dataset.', self::PRINT_LEVEL_INFO);
			return;
		}

		$this->print('DANGER, start deleting', self::PRINT_LEVEL_WARNING);
		$data = $this->row->getData();

		$this->print("Data before: \t" . json_encode(array_values($data)), self::PRINT_LEVEL_INFO);
		$key = array_search($columnId, array_column($data, 'columnId'));
		unset($data[$key]);
		$this->print("Data after: \t" . json_encode(array_values($data)), self::PRINT_LEVEL_INFO);
		$this->row->setData(array_values($data));

		try {
			$this->rowMapper->update($this->row);
			$this->print('Row successfully updated', self::PRINT_LEVEL_SUCCESS);
		} catch (InternalError|PermissionError $e) {
			$this->print('Error while updating row to db.', self::PRINT_LEVEL_ERROR);
			$this->logger->error('Following error occurred during executing occ command "' . self::class . '"', ['exception' => $e]);
		}
	}

	private function print(string $message, ?int $level = null): void {
		if ($level === self::PRINT_LEVEL_SUCCESS) {
			echo '✅ ' . $message;
			echo "\n";
		} elseif ($level === self::PRINT_LEVEL_INFO && $this->output->isVerbose()) {
			echo 'ℹ️  ' . $message;
			echo "\n";
		} elseif ($level === self::PRINT_LEVEL_WARNING) {
			echo '⚠️ ' . $message;
			echo "\n";
		} elseif ($level === self::PRINT_LEVEL_ERROR) {
			echo '❌ ' . $message;
			echo "\n";
		} elseif ($this->output->isVerbose()) {
			echo $message;
			echo "\n";
		}
	}

}
