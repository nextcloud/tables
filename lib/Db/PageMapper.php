<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Page> */
class PageMapper extends QBMapper {
	use TTransactional;

	protected string $table = 'tables_contexts_page';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Page::class);
	}

	/**
	 * Inserts a page under the default technical name `page_<id>`.
	 *
	 * @throws Exception
	 */
	public function insertWithDefaultName(Page $page): Page {
		return $this->atomic(function () use ($page): Page {
			$page = $this->insert($page);
			$page->setTechnicalName(Page::DEFAULT_NAME_PREFIX . $page->getId());
			return $this->update($page);
		}, $this->db);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findById(int $pageId): Page {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @return int[]
	 */
	public function getPageIdsForContext(int $contextId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id')
			->from($this->table)
			->where($qb->expr()->eq('context_id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$pageIds = [];
		while ($row = $result->fetchAssociative()
		) {
			$pageIds[] = (int)$row['id'];
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
