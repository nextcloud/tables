<?php

namespace OCA\Tables\Db\ColumnTypes;

use OCA\Tables\Db\View;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Psr\Log\LoggerInterface;

class NumberColumnQB extends SuperColumnQB implements IColumnTypeQB {
	protected LoggerInterface $logger;
	protected int $platform;

	public function passSearchValue(IQueryBuilder &$qb, string $unformattedSearchValue, string $operator, string $searchValuePlaceHolder): void {
		$qb->setParameter($searchValuePlaceHolder, $unformattedSearchValue, $qb::PARAM_INT);
	}
}
