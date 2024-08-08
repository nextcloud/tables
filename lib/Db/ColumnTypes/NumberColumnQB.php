<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db\ColumnTypes;

use OCP\DB\QueryBuilder\IQueryBuilder;

class NumberColumnQB extends SuperColumnQB implements IColumnTypeQB {

	public function passSearchValue(IQueryBuilder $qb, string $unformattedSearchValue, string $operator, string $searchValuePlaceHolder): void {
		// TODO can we filter for float values?
		$qb->setParameter($searchValuePlaceHolder, $unformattedSearchValue, IQueryBuilder::PARAM_INT);
	}
}
