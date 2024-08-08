<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db\ColumnTypes;

use OCP\DB\QueryBuilder\IQueryBuilder;

class DatetimeColumnQB extends SuperColumnQB implements IColumnTypeQB {

	public function passSearchValue(IQueryBuilder $qb, string $unformattedSearchValue, string $operator, string $searchValuePlaceHolder): void {
		$qb->setParameter($searchValuePlaceHolder, $unformattedSearchValue, IQueryBuilder::PARAM_STR);
	}
}
