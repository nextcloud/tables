<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Command;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\TableService;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveTable extends Command {
	protected TableService $tableService;
	protected LoggerInterface $logger;

	public function __construct(TableService $tableService, LoggerInterface $logger) {
		parent::__construct();
		$this->tableService = $tableService;
		$this->logger = $logger;
	}

	protected function configure(): void {
		$this
			->setName('tables:remove')
			->setDescription('Remove a table.')
			->addArgument(
				'ID',
				InputArgument::REQUIRED,
				'ID for the table.'
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = $input->getArgument('ID');

		try {
			$this->tableService->delete($id, '');
			$output->writeln('Table deleted.');
		} catch (InternalError|PermissionError|Exception $e) {
			$output->writeln('Error occurred: ' . $e->getMessage());
			$this->logger->warning('Following error occurred during executing occ command "' . self::class . '"', ['exception' => $e]);
			return 1;
		}
		return 0;
	}
}
