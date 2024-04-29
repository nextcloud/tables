<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Page> */
class PageMapper extends QBMapper {
	protected string $table = 'tables_contexts_page';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Page::class);
	}

	public function getPageIdsForContext(int $contextId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id')
			->from($this->table)
			->where($qb->expr()->eq('context_id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$pageIds = [];
		while ($row = $result->fetch()
		) {
			$pageIds[] = $row['id'];
		}
		return $pageIds;
	}

	public function deleteByPageId(int $pageId): int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)));

		return $qb->executeStatement();
	}
}
