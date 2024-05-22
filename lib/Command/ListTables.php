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
use OCA\Tables\Service\TableService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListTables extends Command {
	protected TableService $tableService;
	protected LoggerInterface $logger;

	public function __construct(TableService $tableService, LoggerInterface $logger) {
		parent::__construct();
		$this->tableService = $tableService;
		$this->logger = $logger;
	}

	protected function configure(): void {
		$this
			->setName('tables:list')
			->setDescription('List all tables.')
			->addArgument(
				'user-id',
				InputArgument::OPTIONAL,
				'User ID of the user'
			)
			->addOption(
				'count',
				'c',
				InputOption::VALUE_NONE,
				'Show a counter'
			)
			->addOption(
				'no-shares',
				's',
				InputOption::VALUE_NONE,
				'No shared tables'
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
		$showCounter = !!$input->getOption('count');
		$noSharedTables = !!$input->getOption('no-shares');

		$tables = [];
		try {
			if ($userId === null) {
				$tables = $this->tableService->findAll('', true, true);
			} else {
				$tables = $this->tableService->findAll($userId, true, $noSharedTables);
			}
		} catch (InternalError $e) {
			$output->writeln('Error while reading tables from db.');
			$this->logger->warning('Following error occurred during executing occ command "'.self::class.'"', ['exception' => $e]);
			return 1;
		}

		if ($showCounter) {
			$output->writeln(count($tables).' tables');
		} else {
			$out = [];
			foreach ($tables as $table) {
				$arr = $table->jsonSerialize();
				unset($arr['hasShares']);
				unset($arr['isShared']);
				unset($arr['onSharePermissions']);
				unset($arr['rowsCount']);
				unset($arr['ownerDisplayName']);
				$out[] = $arr;
			}
			$output->writeln(json_encode($out, JSON_PRETTY_PRINT));
		}
		return 0;
	}
}
