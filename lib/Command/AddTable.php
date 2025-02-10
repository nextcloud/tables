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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddTable extends Command {
	protected TableService $tableService;
	protected LoggerInterface $logger;

	public function __construct(TableService $tableService, LoggerInterface $logger) {
		parent::__construct();
		$this->tableService = $tableService;
		$this->logger = $logger;
	}

	protected function configure(): void {
		$this
			->setName('tables:add')
			->setDescription('Add a table.')
			->addArgument(
				'user-id',
				InputArgument::REQUIRED,
				'User ID of the user'
			)
			->addArgument(
				'title',
				InputArgument::REQUIRED,
				'Title for the table.'
			)
			->addOption(
				'emoji',
				'e',
				InputOption::VALUE_OPTIONAL,
				'Add an emoji.'
			)
			->addOption(
				'template',
				't',
				InputOption::VALUE_OPTIONAL,
				'Insert structure and data from template.'
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getArgument('user-id');
		$title = $input->getArgument('title');
		$emoji = $input->getOption('emoji') ?: '';
		$template = $input->getOption('template') ?: '';

		try {
			$table = $this->tableService->create($title, $template, $emoji, $userId);

			$arr = $table->jsonSerialize();
			unset($arr['hasShares']);
			unset($arr['isShared']);
			unset($arr['onSharePermissions']);
			unset($arr['rowsCount']);
			unset($arr['ownerDisplayName']);
			$output->writeln(json_encode($arr, JSON_PRETTY_PRINT));
		} catch (InternalError|PermissionError|Exception $e) {
			$output->writeln('Error occurred: ' . $e->getMessage());
			$this->logger->warning('Following error occurred during executing occ command "' . self::class . '"', ['exception' => $e]);
			return 1;
		}
		return 0;
	}
}
