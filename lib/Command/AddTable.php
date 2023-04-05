<?php
/**
 * @copyright Copyright (c) 2023 Florian Steffens <flost-dev@mailbox.org>
 *
 * @author Florian Steffens <flost-dev@mailbox.org>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
			$output->writeln('Error occurred: '.$e->getMessage());
			$this->logger->warning('Following error occurred during executing occ command "'.self::class.'"', ['exception' => $e]);
			return 1;
		}
		return 0;
	}
}
