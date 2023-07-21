<?php

namespace OCA\Tables\Db\ColumnTypes;

use Psr\Log\LoggerInterface;

class SelectionColumnQB extends SuperColumnQB implements IColumnTypeQB {
	protected LoggerInterface $logger;
	protected int $platform;
}
