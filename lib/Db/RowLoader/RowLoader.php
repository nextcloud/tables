<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db\RowLoader;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\RowCellMapperSuper;
use OCA\Tables\Errors\InternalError;

interface RowLoader {
	public const LOADER_CACHED = 'cached';
	public const LOADER_NORMALIZED = 'normalized';

	public const MAX_DB_PARAMETERS = 1_000;

	/**
	 * @param array $rowIds
	 * @param array<int, Column> $columns Column per columnId
	 * @param array<int, RowCellMapperSuper> $mappers Mapper per columnId
	 * @return iterable<Row2>
	 * @throws InternalError
	 */
	public function getRows(array $rowIds, array $columns, array $mappers): iterable;
}
