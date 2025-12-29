<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Helper\ColumnsHelper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class CacheSleeveCells implements IRepairStep {
	public function __construct(
		private IDBConnection $db,
		private ColumnMapper $columnMapper,
		private ColumnsHelper $columnsHelper,
		private RowSleeveMapper $rowSleeveMapper,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return 'Caches cells to the row-sleeves table';
	}

	/**
	 * @inheritDoc
	 */
	public function run(IOutput $output) {
		$cachingSleeveCellsComplete = $this->config->getAppValue('tables', 'cachingSleeveCellsComplete', 'false') === 'true';
		if ($cachingSleeveCellsComplete) {
			return;
		}

		foreach ($this->getTableIds() as $tableId) {
			$columns = $this->columnMapper->findAllByTable($tableId);

			while ($rowIds = $this->getPendingRowIds($tableId)) {
				foreach ($rowIds as $rowId) {
					$cachedCells = [];
					foreach ($columns as $column) {
						$cellMapper = $this->columnsHelper->getCellMapperFromType($column->getType());
						$cells = $cellMapper->findManyByRowAndColumn($rowId, $column->getId());
						foreach ($cells as $cell) {
							if ($cellMapper->hasMultipleValues()) {
								$cachedCells[$column->getId()][] = $cellMapper->toArray($cell);
							} else {
								$cachedCells[$column->getId()] = $cellMapper->toArray($cell);
							}
						}
					}

					$sleeve = $this->rowSleeveMapper->find($rowId);
					$sleeve->setCachedCellsArray($cachedCells);
					$this->rowSleeveMapper->update($sleeve);
				}
			}

			$this->logger->info('Finished caching cells for table ' . $tableId);
		}

		$this->config->setAppValue('tables', 'cachingSleeveCellsComplete', 'true');
	}

	/**
	 * @return int[]
	 */
	public function getTableIds(): array {
		return $this->db->getQueryBuilder()
			->select('id')
			->from('tables_tables')
			->orderBy('id')
			->setMaxResults(PHP_INT_MAX)
			->executeQuery()
			->fetchAll(\PDO::FETCH_COLUMN);

	}

	/**
	 * @return int[]
	 */
	private function getPendingRowIds(int $tableId): array {
		$qb = $this->db->getQueryBuilder();

		return $qb->select('id')
			->from('tables_row_sleeves')
			->where($qb->expr()->isNull('cached_cells'))
			->andWhere($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId, \PDO::PARAM_INT)))
			->orderBy('id')
			->setMaxResults(1000)
			->executeQuery()
			->fetchAll(\PDO::FETCH_COLUMN);
	}
}
