<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\UserMigration;

use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\RowCellDatetime;
use OCA\Tables\Db\RowCellDatetimeMapper;
use OCA\Tables\Db\RowCellNumber;
use OCA\Tables\Db\RowCellNumberMapper;
use OCA\Tables\Db\RowCellSelection;
use OCA\Tables\Db\RowCellSelectionMapper;
use OCA\Tables\Db\RowCellText;
use OCA\Tables\Db\RowCellTextMapper;
use OCA\Tables\Db\RowCellUsergroup;
use OCA\Tables\Db\RowCellUsergroupMapper;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\FavoritesService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\ISizeEstimationMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use Symfony\Component\Console\Output\OutputInterface;

class TablesMigrator implements IMigrator, ISizeEstimationMigrator {
	use TMigratorBasicVersionHandling;

	protected const FILE_TABLES = 'tables.json';
	protected const FILE_CONTEXTS = 'contexts.json';
	protected const FILE_COLUMNS = 'columns.json';
	protected const FILE_ROWS = 'rows.json';
	protected const FILE_VIEWS = 'views.json';
	protected const FILE_FAVORITES = 'favorites.json';
	protected const FILE_SHARES = 'shares.json';
	protected const FILE_ROW_CELL_DATETIME = 'row_cell_datetime.json';
	protected const FILE_ROW_CELL_NUMBERS = 'row_cell_numbers.json';
	protected const FILE_ROW_CELL_TEXT = 'row_cell_text.json';
	protected const FILE_ROW_CELL_SELECTION = 'row_cell_selection.json';
	protected const FILE_ROW_CELL_USERGROUP = 'row_cell_usergroup.json';

	protected const JSON_DEPTH = 512;
	protected const JSON_OPTIONS = JSON_THROW_ON_ERROR;

	public function __construct(
		protected IL10N $l10n,
		protected TableMapper $tableMapper,
		protected ColumnMapper $columnMapper,
		protected RowSleeveMapper $rowSleeveMapper,
		protected ViewMapper $viewMapper,
		protected ContextMapper $contextMapper,
		protected ShareMapper $shareMapper,
		protected ContextNodeRelationMapper $contextNodeRelationMapper,
		protected FavoritesService $favoritesService,
		protected TableService $tableService,
		protected RowCellNumberMapper $rowCellNumberMapper,
		protected RowCellSelectionMapper $rowCellSelectionMapper,
		protected RowCellTextMapper $rowCellTextMapper,
		protected RowCellUsergroupMapper $rowCellUsergroupMapper,
		protected RowCellDatetimeMapper $rowCellDatetimeMapper,
		protected ViewService $viewService,
		protected ColumnService $columnService,
		protected RowService $rowService,
		private ContextService $contextService,
		private ShareService $shareService,
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEstimatedExportSize(IUser $user): int|float {
		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function export(
		IUser $user,
		IExportDestination $exportDestination,
		OutputInterface $output,
	): void {
		try {
			$uid = $user->getUID();

			$favorites = $this->favoritesService->findAll($uid);
			$exportDestination->addFileContents(self::FILE_FAVORITES, json_encode($favorites));

			$tables = $this->tableMapper->findAll($uid);
			$exportDestination->addFileContents(self::FILE_TABLES, json_encode($tables));

			$tableIds = array_map(fn ($t) => $t->getId(), $tables);
			$columns = $this->columnMapper->findAllByTableIds($tableIds);
			$exportDestination->addFileContents(self::FILE_COLUMNS, json_encode($columns));

			$rows = $this->rowSleeveMapper->findAllByTableIds($tableIds);
			$exportDestination->addFileContents(self::FILE_ROWS, json_encode($rows));

			$views = $this->getAllViewsForTableIds($tableIds);
			$exportDestination->addFileContents(self::FILE_VIEWS, json_encode($views));

			$contexts = $this->contextMapper->findAll($uid);
			$exportDestination->addFileContents(self::FILE_CONTEXTS, json_encode($contexts));
			$contextIds = array_map(fn ($c) => $c->getId(), $contexts);

			$shares = $this->shareMapper->findAllSharesForTablesAndContexts($tableIds, $contextIds);
			$exportDestination->addFileContents(self::FILE_SHARES, json_encode($shares));

			$rowIds = array_map(fn ($c) => $c->getId(), $rows);
			$columnIds = array_map(fn ($c) => $c->getId(), $columns);

			$rowCellNumbers = $this->rowCellNumberMapper->findAllByRowIdsAndColumnIds($rowIds, $columnIds);
			$exportDestination->addFileContents(self::FILE_ROW_CELL_NUMBERS, json_encode($rowCellNumbers));

			$rowCellSelection = $this->rowCellSelectionMapper->findAllByRowIdsAndColumnIds($rowIds, $columnIds);
			$exportDestination->addFileContents(self::FILE_ROW_CELL_SELECTION, json_encode($rowCellSelection));

			$rowCellText = $this->rowCellTextMapper->findAllByRowIdsAndColumnIds($rowIds, $columnIds);
			$exportDestination->addFileContents(self::FILE_ROW_CELL_TEXT, json_encode($rowCellText));

			$rowCellDateTime = $this->rowCellDatetimeMapper->findAllByRowIdsAndColumnIds($rowIds, $columnIds);
			$exportDestination->addFileContents(self::FILE_ROW_CELL_DATETIME, json_encode($rowCellDateTime));

			$rowCellUserGroup = $this->rowCellUsergroupMapper->findAllByRowIdsAndColumnIds($rowIds, $columnIds);
			$exportDestination->addFileContents(self::FILE_ROW_CELL_USERGROUP, json_encode($rowCellUserGroup));

		} catch (\Throwable $e) {
			throw new TableMigratorException($e->getMessage(), 0, $e);
		}

	}

	/**
	 * @param array $tableIds
	 *
	 * @return array
	 */
	private function getAllViewsForTableIds(array $tableIds): array {
		$views = [];
		foreach ($tableIds as $tableId) {
			$tableViews = $this->viewMapper->findAll($tableId);
			if (!empty($tableViews)) {
				array_push($views, ...$tableViews);
			}
		}
		return $views;
	}

	/**
	 * {@inheritDoc}
	 */
	public function import(
		IUser $user,
		IImportSource $importSource,
		OutputInterface $output,
	): void {
		if ($importSource->getMigratorVersion($this->getId()) === null) {
			$output->writeln('No version for migrator ' . $this->getId() . ' (' . static::class . '), skipping import…');
			return;
		}
		$output->writeln('Importing tables, columns, rows, contexts, shares, and relations…');

		$tables = json_decode($importSource->getFileContents(self::FILE_TABLES), true, self::JSON_DEPTH, self::JSON_OPTIONS);
		$contexts = json_decode($importSource->getFileContents(self::FILE_CONTEXTS), true, self::JSON_DEPTH, self::JSON_OPTIONS);

		$tableIdMap = [];
		$contextIdMap = [];
		$columnIdMap = [];
		$rowIdMap = [];
		$userId = $user->getUID();
		$connection = $this->tableMapper->getDBConnection();
		$connection->beginTransaction();

		try {
			foreach ($tables as $table) {
				$newTable = $this->tableService->importTable($table, $userId);

				$this->importFavorites($importSource, $newTable, $table);

				$columnIdMap = $this->importColumns($importSource, $newTable, $table, $columnIdMap);
				$rowIdMap = $this->importRows($importSource, $newTable, $table, $rowIdMap);

				$this->importContexts($contexts, $newTable, $table, $contextIdMap);
				$tableIdMap[$table['id']] = $newTable->getId();
			}

			$this->importViews($importSource, $tableIdMap, $columnIdMap, $userId);
			$this->importShares($importSource, $tableIdMap, $contextIdMap, $userId);

			$this->importRowCells(
				json_decode($importSource->getFileContents(self::FILE_ROW_CELL_DATETIME), true, self::JSON_DEPTH, self::JSON_OPTIONS),
				$rowIdMap,
				$columnIdMap,
				RowCellDatetime::class,
				$this->rowCellDatetimeMapper,
				$userId,
			);

			$this->importRowCells(
				json_decode($importSource->getFileContents(self::FILE_ROW_CELL_NUMBERS), true, self::JSON_DEPTH, self::JSON_OPTIONS),
				$rowIdMap,
				$columnIdMap,
				RowCellNumber::class,
				$this->rowCellNumberMapper,
				$userId,
			);

			$this->importRowCells(
				json_decode($importSource->getFileContents(self::FILE_ROW_CELL_TEXT), true, self::JSON_DEPTH, self::JSON_OPTIONS),
				$rowIdMap,
				$columnIdMap,
				RowCellText::class,
				$this->rowCellTextMapper,
				$userId,
			);

			$this->importRowCells(
				json_decode($importSource->getFileContents(self::FILE_ROW_CELL_SELECTION), true, self::JSON_DEPTH, self::JSON_OPTIONS),
				$rowIdMap,
				$columnIdMap,
				RowCellSelection::class,
				$this->rowCellSelectionMapper,
				$userId,
			);

			$this->importRowCells(
				json_decode($importSource->getFileContents(self::FILE_ROW_CELL_USERGROUP), true, self::JSON_DEPTH, self::JSON_OPTIONS),
				$rowIdMap,
				$columnIdMap,
				RowCellUsergroup::class,
				$this->rowCellUsergroupMapper,
				$userId,
			);

			$connection->commit();
		} catch (\Throwable $e) {
			$connection->rollBack();
			throw $e;
		}

		$output->writeln('Import completed.');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getId(): string {
		return 'tables';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDisplayName(): string {
		return $this->l10n->t('Tables');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): string {
		return $this->l10n->t('All tables, columns, rows, contexts, and sharing information including all tables owned or shared, their structure and content');
	}

	/**
	 * @param array $data
	 * @param array $rowIdMap
	 * @param array $columnIdMap
	 * @param string $class
	 * @param object $mapper
	 * @param string $userId
	 *
	 * @return void
	 */
	private function importRowCells(array $data, array $rowIdMap, array $columnIdMap, string $class, object $mapper, string $userId): void {
		foreach ($data as $cellData) {
			$oldRowId = $cellData['rowId'];
			$oldColumnId = $cellData['columnId'];
			if (isset($rowIdMap[$oldRowId]) && isset($columnIdMap[$oldColumnId])) {
				$cell = new $class();
				$cell->setRowId($rowIdMap[$oldRowId]);
				$cell->setColumnId($columnIdMap[$oldColumnId]);
				$cell->setValue($cellData['value'] ?? null);
				$cell->setLastEditBy($userId);
				$cell->setLastEditAt($cellData['lastEditAt'] ?? null);
				$mapper->insert($cell);
			}
		}
	}

	/**
	 * @param IImportSource $importSource
	 * @param array $tableIdMap
	 * @param array $columnIdMap
	 * @param string $userId
	 *
	 * @return void
	 */
	private function importViews(IImportSource $importSource, array $tableIdMap, array $columnIdMap, string $userId): void {
		$views = json_decode($importSource->getFileContents(self::FILE_VIEWS), true, self::JSON_DEPTH, self::JSON_OPTIONS);
		foreach ($views as $view) {
			if (isset($tableIdMap[$view['tableId']])) {
				$newTableId = $tableIdMap[$view['tableId']];
				if (isset($view['columnSettings']) && is_array($view['columnSettings'])) {
					foreach ($view['columnSettings'] as &$setting) {
						if (isset($setting['columnId']) && isset($columnIdMap[$setting['columnId']])) {
							$setting['columnId'] = $columnIdMap[$setting['columnId']];
						}
					}
					unset($setting);
				}
				$this->viewService->importView($newTableId, $view, $userId);
			}
		}
	}

	/**
	 * @param IImportSource $importSource
	 * @param Table $newTable
	 * @param array $table
	 *
	 * @return void
	 */
	private function importFavorites(IImportSource $importSource, Table $newTable, array $table): void {
		$favorites = json_decode($importSource->getFileContents(self::FILE_FAVORITES), true, self::JSON_DEPTH, self::JSON_OPTIONS);
		foreach ($favorites as $favorite) {
			if ($table['id'] === $favorite['node_id']) {
				$this->favoritesService->importFavorite($favorite['node_type'], $newTable);
			}
		}
	}

	/**
	 * @param IImportSource $importSource
	 * @param Table $newTable
	 * @param array $table
	 * @param array $columnIdMap
	 *
	 * @return array
	 */
	private function importColumns(IImportSource $importSource, Table $newTable, array $table, array $columnIdMap): array {
		$columns = json_decode($importSource->getFileContents(self::FILE_COLUMNS), true, self::JSON_DEPTH, self::JSON_OPTIONS);
		foreach ($columns as $column) {
			if ($table['id'] === $column['tableId']) {
				$newColumnId = $this->columnService->importColumn($newTable, $column);
				$columnIdMap[$column['id']] = $newColumnId;
			}
		}
		return $columnIdMap;
	}

	/**
	 * @param IImportSource $importSource
	 * @param Table $newTable
	 * @param array $table
	 * @param array $rowIdMap
	 *
	 * @return array
	 */
	private function importRows(IImportSource $importSource, Table $newTable, array $table, array $rowIdMap): array {
		$rows = json_decode($importSource->getFileContents(self::FILE_ROWS), true, self::JSON_DEPTH, self::JSON_OPTIONS);
		foreach ($rows as $row) {
			if ($table['id'] === $row['tableId']) {
				$newRowId = $this->rowService->importRow($newTable, $row);
				$rowIdMap[$row['id']] = $newRowId;
			}
		}
		return $rowIdMap;
	}

	/**
	 * @param array $contexts
	 * @param Table $newTable
	 * @param array $table
	 * @param array $contextIdMap
	 *
	 * @return void
	 */
	private function importContexts(array $contexts, Table $newTable, array $table, array &$contextIdMap): void {
		foreach ($contexts as $context) {
			$newContext = $this->contextService->importContext($newTable, $context, $table['id']);
			if ($newContext !== null) {
				$contextIdMap[$context['id']] = $newContext->getId();
			}
		}
	}

	/**
	 * @param IImportSource $importSource
	 * @param array $tableIdMap
	 * @param array $contextIdMap
	 * @param string $userId
	 *
	 * @return void
	 */
	private function importShares(IImportSource $importSource, array $tableIdMap, array $contextIdMap, string $userId): void {
		$shares = json_decode($importSource->getFileContents(self::FILE_SHARES), true, self::JSON_DEPTH, self::JSON_OPTIONS);
		foreach ($shares as $share) {
			if ($share['nodeType'] === 'table' && isset($tableIdMap[$share['nodeId']])) {
				$this->shareService->importShare($tableIdMap[$share['nodeId']], $share, $userId);
			} elseif ($share['nodeType'] === 'context' && isset($contextIdMap[$share['nodeId']])) {
				$this->shareService->importShare($contextIdMap[$share['nodeId']], $share, $userId);
			}
		}
	}
}
