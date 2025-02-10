<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Command;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\TableService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChangeOwnershipTable extends Command {
	protected TableService $tableService;
	protected LoggerInterface $logger;

	public function __construct(TableService $tableService, LoggerInterface $logger) {
		parent::__construct();
		$this->tableService = $tableService;
		$this->logger = $logger;
	}

	protected function configure(): void {
		$this
			->setName('tables:owner')
			->setDescription('Set new owner for a table.')
			->addArgument(
				'ID',
				InputArgument::REQUIRED,
				'ID of the table'
			)
			->addArgument(
				'user-id',
				InputArgument::REQUIRED,
				'User-id for the new owner.'
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
		$newOwnerUserId = $input->getArgument('user-id');

		try {
			$table = $this->tableService->setOwner($id, $newOwnerUserId, '');
			$output->writeln(json_encode($table->jsonSerialize(), JSON_PRETTY_PRINT));
		} catch (InternalError $e) {
			$output->writeln('Error occurred: ' . $e->getMessage());
			$this->logger->warning('Following error occurred during executing occ command "' . self::class . '"', ['exception' => $e]);
			return 1;
		} catch (NotFoundError $e) {
			$output->writeln('Not found error occurred: ' . $e->getMessage());
			$this->logger->warning('Following error occurred during executing occ command "' . self::class . '"', ['exception' => $e]);
			return 1;
		} catch (PermissionError $e) {
			$output->writeln('Permission error occurred: ' . $e->getMessage());
			$this->logger->warning('Following error occurred during executing occ command "' . self::class . '"', ['exception' => $e]);
			return 1;
		}
		return 0;
	}
}
