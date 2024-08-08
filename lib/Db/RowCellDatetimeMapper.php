<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\IDBConnection;

/** @template-extends RowCellMapperSuper<RowCellDatetime, string, string> */
class RowCellDatetimeMapper extends RowCellMapperSuper {
	protected string $table = 'tables_row_cells_datetime';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellDatetime::class);
	}
}
