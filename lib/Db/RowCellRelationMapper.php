<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends RowCellMapperSuper<RowCellRelation, int|null, int|null> */
class RowCellRelationMapper extends RowCellMapperSuper {
	protected string $table = 'tables_row_cells_relation';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellRelation::class);
	}

	/**
	 * @inheritDoc
	 */
	public function hasMultipleValues(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getDbParamType() {
		return IQueryBuilder::PARAM_INT;
	}

	public function formatRowData(Column $column, array $row) {
		$value = $row['value'];
		return (int)$value;
	}
}
