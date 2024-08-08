<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\IDBConnection;

/** @template-extends RowCellMapperSuper<RowCellText, string, string> */
class RowCellTextMapper extends RowCellMapperSuper {
	protected string $table = 'tables_row_cells_text';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellText::class);
	}
}
