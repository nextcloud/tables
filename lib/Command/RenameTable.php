<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Command;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Service\TableService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RenameTable extends Command {
	protected TableService $tableService;
	protected LoggerInterface $logger;

	public function __construct(TableService $tableService, LoggerInterface $logger) {
		parent::__construct();
		$this->tableService = $tableService;
		$this->logger = $logger;
	}

	protected function configure(): void {
		$this
			->setName('tables:update')
			->setAliases(['tables:rename'])
			->setDescription('Rename a table.')
			->addArgument(
				'ID',
				InputArgument::REQUIRED,
				'ID of the table'
			)
			->addArgument(
				'title',
				InputArgument::OPTIONAL,
				'New title for the table.'
			)
			->addOption(
				'description',
				'd',
				InputOption::VALUE_REQUIRED,
				'New description for the table.'
			)
			->addOption(
				'emoji',
				'e',
				InputOption::VALUE_OPTIONAL,
				'New emoji.'
			)
			->addOption(
				'archived',
				'a',
				InputOption::VALUE_NONE,
				'Archived'
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
		$title = $input->getArgument('title');
		$emoji = $input->getOption('emoji');
		$archived = $input->getOption('archived');
		$description = $input->getOption('description');

		try {
			$table = $this->tableService->update($id, $title, $emoji, $description, $archived, '');

			$arr = $table->jsonSerialize();
			unset($arr['hasShares']);
			unset($arr['isShared']);
			unset($arr['onSharePermissions']);
			unset($arr['rowsCount']);
			unset($arr['ownerDisplayName']);
			$output->writeln(json_encode($arr, JSON_PRETTY_PRINT));
		} catch (InternalError $e) {
			$output->writeln('Error occurred: ' . $e->getMessage());
			$this->logger->warning('Following error occurred during executing occ command "' . self::class . '"', ['exception' => $e]);
			return 1;
		}
		return 0;
	}
}
