<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use OCA\Tables\Db\LegacyRowMapper;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\TableService;
use OCP\DB\Exception;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

class NewDbStructureRepairStep implements IRepairStep {

	protected LoggerInterface $logger;
	protected TableService $tableService;
	protected LegacyRowMapper $legacyRowMapper;
	protected Row2Mapper $rowMapper;
	protected ColumnService $columnService;
	protected IConfig $config;

	public function __construct(LoggerInterface $logger, TableService $tableService, ColumnService $columnService, LegacyRowMapper $legacyRowMapper, Row2Mapper $rowMapper, IConfig $config) {
		$this->logger = $logger;
		$this->tableService = $tableService;
		$this->columnService = $columnService;
		$this->legacyRowMapper = $legacyRowMapper;
		$this->rowMapper = $rowMapper;
		$this->config = $config;
	}

	/**
	 * Returns the step's name
	 */
	public function getName(): string {
		return 'Copy the data into the new db structure';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		$legacyRowTransferRunComplete = $this->config->getAppValue('tables', 'legacyRowTransferRunComplete', 'false');

		if ($legacyRowTransferRunComplete === 'true') {
			return;
		}

		$output->info('Look for tables');
		try {
			$tables = $this->tableService->findAll('', true, true, false);
			$output->info('Found ' . count($tables) . ' table(s)');
		} catch (InternalError $e) {
			$output->warning('Error while fetching tables. Will aboard.');
			return;
		}
		$this->transferDataForTables($tables, $output);
		$this->config->setAppValue('tables', 'legacyRowTransferRunComplete', 'true');
	}

	/**
	 * @param Table[] $tables
	 * @return void
	 */
	private function transferDataForTables(array $tables, IOutput $output) {
		$i = 1;
		foreach ($tables as $table) {
			$output->info('-- Start transfer for table ' . $table->getId() . ' (' . $table->getTitle() . ') [' . $i . '/' . count($tables) . ']');
			try {
				$this->transferTable($table, $output);
			} catch (InternalError|PermissionError|Exception|Throwable $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				$output->warning('Could not transfer data. Continue with next table. The logs will have more information about the error: ' . $e->getMessage());
			}
			$i++;
		}
	}

	/**
	 * @throws PermissionError
	 * @throws InternalError
	 * @throws Exception
	 */
	private function transferTable(Table $table, IOutput $output) {
		$columns = $this->columnService->findAllByTable($table->getId(), '');
		$output->info('---- Found ' . count($columns) . ' columns');

		$legacyRows = $this->legacyRowMapper->findAllByTable($table->getId());
		$output->info('---- Found ' . count($legacyRows) . ' rows');

		$output->startProgress(count($legacyRows));
		foreach ($legacyRows as $legacyRow) {
			$this->legacyRowMapper->transferLegacyRow($legacyRow, $columns);
			$output->advance(1);
		}
		$output->finishProgress();
	}
}
