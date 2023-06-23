<?php

namespace OCA\Tables\DB\ColumnTypes;

use OCA\Tables\Db\View;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Psr\Log\LoggerInterface;

class TextColumnQB extends SuperColumnQB implements IColumnTypeQB {
	protected LoggerInterface $logger;
	protected int $platform;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	/**
	 * Add the where clauses for fetching rows depending on a view
	 * will respect filters and sorting
	 *
	 * @param IQueryBuilder $qb
	 * @param View $view
	 * @return void
	 */
	public function addWhereForFindAllByView(IQueryBuilder &$qb, View $view) {
		// TODO
	}

}
