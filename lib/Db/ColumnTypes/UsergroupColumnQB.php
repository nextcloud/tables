<?php

namespace OCA\Tables\Db\ColumnTypes;

use OCP\DB\QueryBuilder\IQueryBuilder;

class UsergroupColumnQB extends SuperColumnQB implements IColumnTypeQB {
	public function passSearchValue(IQueryBuilder $qb, string $unformattedSearchValue, string $operator, string $searchValuePlaceHolder): void {
		// TODO how to handle searching for multiple users/groups?
		$qb->setParameter($searchValuePlaceHolder, $unformattedSearchValue, IQueryBuilder::PARAM_STR);
	}
}