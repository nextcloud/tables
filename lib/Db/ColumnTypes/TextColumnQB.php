<?php

namespace OCA\Tables\Db\ColumnTypes;

use Psr\Log\LoggerInterface;

class TextColumnQB extends SuperColumnQB implements IColumnTypeQB {
	protected LoggerInterface $logger;
	protected int $platform;
}
