<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

/** @template-extends QBMapper<PageContent> */
class PageContentMapper extends QBMapper {
	protected string $table = 'tables_contexts_page_content';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, PageContent::class);
	}

	/**
	 * @throws Exception
	 */
	public function findByPageAndNodeRelation(int $pageId, int $nodeRelId): ?PageContent {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->andX(
				$qb->expr()->eq('page_id', $qb->createNamedParameter($pageId)),
				$qb->expr()->eq('node_rel_id', $qb->createNamedParameter($nodeRelId)),
			));

		$result = $qb->executeQuery();
		$r = $result->fetch();
		return $r ? $this->mapRowToEntity($r) : null;
	}

	public function findByNodeRelation(int $nodeRelId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->andX(
				$qb->expr()->eq('node_rel_id', $qb->createNamedParameter($nodeRelId)),
			));

		return $this->findEntities($qb);
	}
}
