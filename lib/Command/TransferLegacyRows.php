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

use OCA\Tables\Db\LegacyRowMapper;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\TableService;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TransferLegacyRows extends Command {
	protected TableService $tableService;
	protected LoggerInterface $logger;
	protected LegacyRowMapper $legacyRowMapper;
	protected Row2Mapper $rowMapper;
	protected ColumnService $columnService;

	public function __construct(TableService $tableService, LoggerInterface $logger, LegacyRowMapper $legacyRowMapper, Row2Mapper $rowMapper, ColumnService $columnService) {
		parent::__construct();
		$this->tableService = $tableService;
		$this->logger = $logger;
		$this->legacyRowMapper = $legacyRowMapper;
		$this->rowMapper = $rowMapper;
		$this->columnService = $columnService;
	}

	protected function configure(): void {
		$this
			->setName('tables:legacy:transfer:rows')
			->setDescription('Transfer table legacy rows to new schema.')
			->addArgument(
				'table-ids',
				InputArgument::OPTIONAL,
				'IDs of tables for the which data is to be transferred. (Multiple comma seperated possible)'
			)
			->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'Transfer all table data.'
			)
			->addOption(
				'no-delete',
				null,
				InputOption::VALUE_OPTIONAL,
				'Set to not delete data from new db structure if any.'
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$tableIds = $input->getArgument('table-ids');
		$optionAll = !!$input->getOption('all');
		$optionNoDelete = $input->getOption('no-delete') ?: null;

		if ($optionAll) {
			$output->writeln("Look for tables");
			try {
				$tables = $this->tableService->findAll('', true, true, false);
				$output->writeln("Found ". count($tables) . " table(s)");
			} catch (InternalError $e) {
				$output->writeln("Error while fetching tables. Will aboard.");
				return 1;
			}
		} elseif ($tableIds) {
			$output->writeln("Look for given table(s)");
			$tableIds = explode(',', $tableIds);
			$tables = [];
			foreach ($tableIds as $tableId) {
				try {
					$tables[] = $this->tableService->find((int)ltrim($tableId), true, '');
				} catch (InternalError|NotFoundError|PermissionError $e) {
					$output->writeln("Could not load table id " . $tableId . ". Will continue.");
				}
			}
		} else {
			$output->writeln("🤷🏻‍ Add at least one table id or add the option --all to transfer all tables.");
			return 1;
		}
		if (!$optionNoDelete) {
			$this->deleteDataForTables($tables, $output);
		}
		$this->transferDataForTables($tables, $output);

		return 0;
	}

	/**
	 * @param Table[] $tables
	 * @return void
	 */
	private function transferDataForTables(array $tables, OutputInterface $output) {
		$i = 1;
		foreach ($tables as $table) {
			$output->writeln("-- Start transfer for table " . $table->getId() . " (" . $table->getTitle() . ") [" . $i . "/" . count($tables) . "]");
			try {
				$this->transferTable($table, $output);
			} catch (InternalError|PermissionError|Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				$output->writeln("⚠️  Could not transfer data. Continue with next table. The logs will have more information about the error.");
			}
			$i++;
		}
	}

	/**
	 * @throws PermissionError
	 * @throws InternalError
	 * @throws Exception
	 */
	private function transferTable(Table $table, OutputInterface $output) {
		$columns = $this->columnService->findAllByTable($table->getId(), null, '');
		$output->writeln("---- Found " . count($columns) . " columns");

		$legacyRows = $this->legacyRowMapper->findAllByTable($table->getId());
		$output->writeln("---- Found " . count($legacyRows) . " rows");

		foreach ($legacyRows as $legacyRow) {
			$this->legacyRowMapper->transferLegacyRow($legacyRow, $columns);
		}
		$output->writeln("---- ✅  All rows transferred.");
	}

	/**
	 * @param Table[] $tables
	 * @param OutputInterface $output
	 * @return void
	 */
	private function deleteDataForTables(array $tables, OutputInterface $output) {
		$output->writeln("Start deleting data for tables that should be transferred.");
		foreach ($tables as $table) {
			try {
				$columns = $this->columnService->findAllByTable($table->getId(), null, '');
			} catch (InternalError|PermissionError $e) {
				$output->writeln("Could not delete data for table " . $table->getId());
				break;
			}
			$this->rowMapper->deleteAllForTable($table->getId(), $columns);
			$output->writeln("🗑️  Data for table " . $table->getId() . " (" . $table->getTitle() . ")" . " removed.");
		}
	}
}
