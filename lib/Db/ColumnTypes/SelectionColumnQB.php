<?php

namespace OCA\Tables\Db\ColumnTypes;

use OCP\DB\QueryBuilder\IQueryBuilder;

class SelectionColumnQB extends SuperColumnQB implements IColumnTypeQB {
	public function passSearchValue(IQueryBuilder $qb, string $unformattedSearchValue, string $operator, string $searchValuePlaceHolder): void {
		if(substr($unformattedSearchValue, 0, 1) === '@') {
			$parts = explode('-', $unformattedSearchValue);
			$selectedId = intval($parts[2]);
			$qb->setParameter($searchValuePlaceHolder, $selectedId, IQueryBuilder::PARAM_INT);
		} else {
			$qb->setParameter($searchValuePlaceHolder, $unformattedSearchValue, IQueryBuilder::PARAM_STR);
		}
	}
}
