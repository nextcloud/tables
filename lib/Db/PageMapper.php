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
			$page->resetUpdatedFields();
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
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findByTechnicalName(int $contextId, string $technicalName): Page {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('context_id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('technical_name', $qb->createNamedParameter($technicalName)));

		return $this->findEntity($qb);
	}

	/**
	 * Raises the version of a page, the first write of a save. It locks the page row until
	 * the transaction ends, so saves and the delete of one page run one after the other.
	 *
	 * @param ?int $expected the version the save was based on, or null to take any
	 * @return bool false when the page is gone or has another version
	 * @throws Exception
	 */
	public function raiseVersion(int $pageId, ?int $expected): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->table)
			->set('version', $qb->func()->add('version', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)));
		if ($expected !== null) {
			$qb->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expected, IQueryBuilder::PARAM_INT)));
		}
		return $qb->executeStatement() === 1;
	}

	/**
	 * Locks an application until the transaction ends, so a page is never added to one
	 * that is being deleted.
	 *
	 * @return bool false when the application is gone
	 * @throws Exception
	 */
	public function lockContext(int $contextId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('tables_contexts_context')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)));
		// SQLite has no row locks and runs one write transaction at a time anyway
		if ($this->db->getDatabaseProvider() !== IDBConnection::PLATFORM_SQLITE) {
			$qb->forUpdate();
		}
		$result = $qb->executeQuery();
		$found = $result->fetchOne() !== false;
		$result->closeCursor();
		return $found;
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
