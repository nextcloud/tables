<?php

namespace OCA\Tables\Migration;

use OCA\Tables\Db\LegacyRowMapper;
use OCA\Tables\Db\Row;
use OCA\Tables\Db\RowMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\TableService;
use OCP\DB\Exception;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class NewDbStructureRepairStep implements IRepairStep {

	protected LoggerInterface $logger;
	protected TableService $tableService;
	protected LegacyRowMapper $legacyRowMapper;
	protected RowMapper $rowMapper;
	protected ColumnService $columnService;

	public function __construct(LoggerInterface $logger, TableService $tableService, ColumnService $columnService, LegacyRowMapper $legacyRowMapper, RowMapper $rowMapper) {
		$this->logger = $logger;
		$this->tableService = $tableService;
		$this->columnService = $columnService;
		$this->legacyRowMapper = $legacyRowMapper;
		$this->rowMapper = $rowMapper;
	}

	/**
	 * Returns the step's name
	 */
	public function getName() {
		return 'Copy the data into the new db structure';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		$output->info("Look for tables");
		try {
			$tables = $this->tableService->findAll('', true, true, false);
			$output->info("Found ". count($tables) . " table(s)");
		} catch (InternalError $e) {
			$output->warning("Error while fetching tables. Will aboard.");
			return;
		}
		$this->transferDataForTables($tables, $output);
	}

	/**
	 * @param Table[] $tables
	 * @return void
	 */
	private function transferDataForTables(array $tables, IOutput $output) {
		foreach ($tables as $table) {
			$output->info("-- Start transfer for table " . $table->getId() . " (" . $table->getTitle() . ")");
			try {
				$this->transferTable($table, $output);
			} catch (InternalError|PermissionError|Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				$output->warning("Could not transfer data. Continue with next table. The logs will have more information about the error.");
			}
		}
	}

	/**
	 * @throws PermissionError
	 * @throws InternalError
	 * @throws Exception
	 */
	private function transferTable(Table $table, IOutput $output) {
		$columns = $this->columnService->findAllByTable($table->getId(), null, '');
		$output->info("---- Found " . count($columns) . " columns");

		$legacyRows = $this->legacyRowMapper->findAllByTable($table->getId());
		$output->info("---- Found " . count($legacyRows) . " rows");

		$output->startProgress(count($legacyRows));
		foreach ($legacyRows as $legacyRow) {
			$row = new Row();
			$row->setId($legacyRow->getId());
			$row->setTableId($legacyRow->getTableId());
			$row->setCreatedBy($legacyRow->getCreatedBy());
			$row->setCreatedAt($legacyRow->getCreatedAt());
			$row->setLastEditBy($legacyRow->getLastEditBy());
			$row->setLastEditAt($legacyRow->getLastEditAt());
			$row->setData($legacyRow->getDataArray());
			$this->rowMapper->insert($row, $columns);

			$output->advance(1);
		}
		$output->finishProgress();
	}
}
