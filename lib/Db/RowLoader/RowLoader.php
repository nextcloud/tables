<?php

namespace OCA\Tables\Db\RowLoader;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\RowCellMapperSuper;
use OCA\Tables\Errors\InternalError;

interface RowLoader {
	public const LOADER_CACHED = 'cached';
	public const LOADER_NORMALIZED = 'normalized';

	public const MAX_DB_PARAMETERS = 65535;

	/**
	 * @param array $rowIds
	 * @param array<int, Column> $columns Column per columnId
	 * @param array<int, RowCellMapperSuper> $mappers Mapper per columnId
	 * @return iterable<Row2>
	 * @throws InternalError
	 */
	public function getRows(array $rowIds, array $columns, array $mappers): iterable;
}
