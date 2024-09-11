<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<PageContent> */
class PageContentMapper extends QBMapper {
	protected string $table = 'tables_contexts_page_content';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, PageContent::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findById(int $pageContentId): PageContent {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($pageContentId)));

		return $this->mapRowToEntity($this->findOneQuery($qb));
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

	public function deleteByPageId(int $pageId): int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->table)
			->where($qb->expr()->eq('page_id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)));

		return $qb->executeStatement();
	}

	public function deleteByNodeRelIds(array $nodeRelIds): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->in('node_rel_id', $qb->createNamedParameter($nodeRelIds, IQueryBuilder::PARAM_INT_ARRAY), ':nodeRelIds'));
		$qb->executeStatement();
	}
}
