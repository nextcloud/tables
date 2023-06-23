<?php

namespace OCA\Tables\DB\ColumnTypes;

use OCP\DB\QueryBuilder\IQueryBuilder;
use Psr\Log\LoggerInterface;

class SuperColumnQB implements IColumnTypeQB {
	protected LoggerInterface $logger;
	protected int $platform;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	public function setPlatform(int $platform) {
		$this->platform = $platform;
	}

	public function addWhereForFindAllWithColumn(IQueryBuilder &$qb, int $columnId): void {
		if ($this->platform === self::DB_PLATFORM_PGSQL) {
			// due to errors using doctrine with json, I paste the columnId inline.
			// columnId is a number, ensured by the parameter definition
			$qb->where('data::jsonb @> \'[{"columnId": ' . $columnId . '}]\'::jsonb');
		} elseif ($this->platform === self::DB_PLATFORM_SQLITE) {
			$qb->from($qb->createFunction('json_each(data)'));
			$qb->where('json_extract(value, "$.columnId") = :columnId');
		} else {
			$qb->where('JSON_CONTAINS(JSON_VALUE(data, \'$.columnId\'), :columnId, \'$\') = 1');
		}
		$qb->setParameter('columnId', $columnId);
	}

}
