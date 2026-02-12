<?php

namespace OCA\Tables\Db\RowLoader;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\RowCellMapperSuper;
use OCA\Tables\Db\RowSleeve;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Errors\InternalError;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

/**
 * Loads rows using `tables_row_sleeves.cached_cells` column
 */
class CachedRowLoader implements RowLoader {
	public function __construct(
		private RowSleeveMapper $rowSleeveMapper,
		private LoggerInterface $logger,
	) {
	}

	public function getRows(array $rowIds, array $columns, array $mappers): iterable {
		foreach (array_chunk($rowIds, self::MAX_DB_PARAMETERS) as $chunkedRowIds) {
			yield from $this->getRowsChunk($chunkedRowIds, $columns, $mappers);
		}
	}

	/**
	 * @param array $rowIds
	 * @param array<int, Column> $columns Column per columnId
	 * @param array<int, RowCellMapperSuper> $mappers Mapper per columnId
	 * @return Row2[]
	 * @throws InternalError
	 */
	private function getRowsChunk(array $rowIds, array $columns, array $mappers): array {
		try {
			$sleeves = $this->rowSleeveMapper->findMultiple($rowIds);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage(), $e->getCode(), $e);
		}

		return $this->parseResult($sleeves, $columns, $mappers);
	}

	/**
	 * @param RowSleeve[] $sleeves
	 * @param array<int, Column> $columns Column per columnId
	 * @param array<int, RowCellMapperSuper> $mappers Mapper per columnId
	 * @return Row2[]
	 */
	private function parseResult(array $sleeves, array $columns, $mappers): array {
		$rows = [];
		foreach ($sleeves as $sleeve) {
			$id = (int)$sleeve['id'];
			$row = new Row2();
			$row->setId($id);
			$row->setCreatedBy($sleeve['created_by']);
			$row->setCreatedAt($sleeve['created_at']);
			$row->setLastEditBy($sleeve['last_edit_by']);
			$row->setLastEditAt($sleeve['last_edit_at']);
			$row->setTableId((int)$sleeve['table_id']);

			$cachedCells = json_decode($sleeve['cached_cells'] ?? '{}', true);
			foreach ($columns as $columnId => $column) {
				if (empty($cachedCells[$columnId])) {
					continue;
				}

				$cellMapper = $mappers[$columnId];
				if ($cellMapper->hasMultipleValues()) {
					$value = array_map(static fn ($rowData) => $cellMapper->formatRowData($column, $rowData),
						$cachedCells[$columnId]);
				} else {
					$value = $cellMapper->formatRowData($column, $cachedCells[$columnId]);
				}

				$row->addCell($columnId, $value);
			}

			$rows[] = $row;
		}

		return $rows;
	}
}
