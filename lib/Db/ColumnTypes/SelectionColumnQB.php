<?php

namespace OCA\Tables\Db\ColumnTypes;

use OCA\Tables\Db\View;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Psr\Log\LoggerInterface;

class SelectionColumnQB extends SuperColumnQB implements IColumnTypeQB {
	protected LoggerInterface $logger;
	protected int $platform;
}
