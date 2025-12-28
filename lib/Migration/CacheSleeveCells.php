<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Config\ConfigLexicon;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Helper\ColumnsHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class CacheSleeveCells implements IRepairStep {
	private const ROW_BATCH_SIZE = 1_000;

	public function __construct(
		private IDBConnection $db,
		private ColumnMapper $columnMapper,
		private ColumnsHelper $columnsHelper,
		private RowSleeveMapper $rowSleeveMapper,
		private IAppConfig $appConfig,
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
		if ($this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::CACHING_SLEEVE_CELLS_COMPLETE)) {
			return;
		}

		$pendingRowsQuery = $this->buildPendingRowIdsQuery();
		foreach ($this->getTableIds() as $tableId) {
			$pendingRowsQuery->setParameter('tableId', $tableId, IQueryBuilder::PARAM_INT);
			$columns = $this->columnMapper->findAllByTable($tableId);

			while ($rowIds = $this->fetchPendingRowIds($pendingRowsQuery)) {
				foreach ($rowIds as $rowId) {
					$this->cacheCellsForRow($rowId, $columns);
				}
			}

			$this->logger->info('Finished caching cells for table ' . $tableId);
		}

		$this->appConfig->setValueBool(Application::APP_ID, ConfigLexicon::CACHING_SLEEVE_CELLS_COMPLETE, true);
	}

	/**
	 * @param Column[] $columns
	 */
	private function cacheCellsForRow(int $rowId, array $columns): void {
		try {
			$sleeve = $this->rowSleeveMapper->find($rowId);
		} catch (DoesNotExistException) {
			// the row was deleted while the migration is running
			return;
		}

		$cachedCells = $this->columnsHelper->getCachedCellsForRow($rowId, $columns);
		$sleeve->setCachedCellsArray($cachedCells);
		$this->rowSleeveMapper->update($sleeve);
	}

	/**
	 * @return int[]
	 */
	private function getTableIds(): array {
		return $this->db->getQueryBuilder()
			->select('id')
			->from('tables_tables')
			->orderBy('id')
			->executeQuery()
			->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * @return int[]
	 */
	private function fetchPendingRowIds(IQueryBuilder $pendingRowsQuery): array {
		return $pendingRowsQuery->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
	}

	private function buildPendingRowIdsQuery(): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();

		return $qb->select('id')
			->from('tables_row_sleeves')
			->where($qb->expr()->isNull('cached_cells'))
			->andWhere($qb->expr()->eq('table_id', $qb->createParameter('tableId')))
			->orderBy('id')
			->setMaxResults(self::ROW_BATCH_SIZE);
	}
}
