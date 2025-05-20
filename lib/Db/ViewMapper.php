<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\Cache\CappedMemoryCache;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<View> */
class ViewMapper extends QBMapper {
	protected string $table = 'tables_views';

	protected CappedMemoryCache $cache;

	public function __construct(
		IDBConnection $db,
		private UserHelper $userHelper,
	) {
		parent::__construct($db, $this->table, View::class);
		$this->cache = new CappedMemoryCache();
	}

	/**
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): View {
		$cacheKey = (string)$id;
		if (!isset($this->cache[$cacheKey])) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('v.*', 't.ownership')
				->from($this->table, 'v')
				->innerJoin('v', 'tables_tables', 't', 't.id = v.table_id')
				->where($qb->expr()->eq('v.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
			$entity = $this->findEntity($qb);
			$this->cache[$cacheKey] = $entity;
		}
		return $this->cache[$cacheKey];
	}

	public function delete(Entity $entity): View {
		unset($this->cache[(string)$entity->getId()]);
		return parent::delete($entity);
	}

	public function insert(Entity $entity): View {
		$entity = parent::insert($entity);
		$this->cache[(string)$entity->getId()] = $entity;
		return $entity;
	}

	/**
	 * @return View[]
	 * @throws Exception
	 */
	public function findAll(?int $tableId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('v.*', 't.ownership')
			->from($this->table, 'v')
			->innerJoin('v', 'tables_tables', 't', 't.id = v.table_id');

		if ($tableId !== null) {
			$qb->where($qb->expr()->eq('v.table_id', $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT)));
		}
		return $this->findEntities($qb);
	}

	/**
	 * @return View[]
	 * @throws Exception
	 */
	public function search(?string $term = null, ?string $userId = null, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$shareTableQuery = $this->db->getQueryBuilder();
		$shareQueryViewsSharedViaUser = $this->db->getQueryBuilder();
		$shareQueryViewsSharedViaGroup = $this->db->getQueryBuilder();
		$userGroups = $userId ? $this->userHelper->getGroupIdsForUser($userId) : null;

		// get view ids, that are shared with the given user
		// only makes sense if a user is given, otherwise will always get all shares doubled
		if ($userId) {
			$shareQueryViewsSharedViaUser->selectDistinct('node_id')
				->from('tables_shares')
				->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter('view', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter('user', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

			if ($userGroups) {
				$shareQueryViewsSharedViaGroup->selectDistinct('node_id')
					->from('tables_shares')
					->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter('view', IQueryBuilder::PARAM_STR)))
					->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter('group', IQueryBuilder::PARAM_STR)))
					->andWhere($qb->expr()->in('receiver', $qb->createNamedParameter($userGroups, IQueryBuilder::PARAM_STR_ARRAY)));
			}

			$shareTableQuery->selectDistinct('node_id')
				->from('tables_shares')
				->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter('table', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		}

		$qb->select('v.*', 't.ownership')
			->from($this->table, 'v')
			->leftJoin('v', 'tables_tables', 't', 't.id = v.table_id');

		if ($userId) {
			$qb->where($qb->expr()->eq('ownership', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->orWhere($shareQueryViewsSharedViaUser->expr()->in('v.id', $qb->createFunction($shareQueryViewsSharedViaUser->getSQL()), IQueryBuilder::PARAM_INT_ARRAY));
			if ($userGroups) {
				$qb->orWhere($shareQueryViewsSharedViaGroup->expr()->in('v.id', $qb->createFunction($shareQueryViewsSharedViaGroup->getSQL()), IQueryBuilder::PARAM_INT_ARRAY));
			}
		}

		if ($term) {
			$qb->andWhere($qb->expr()->iLike(
				'v.title',
				$qb->createNamedParameter(
					'%' . $this->db->escapeLikeParameter($term) . '%',
					IQueryBuilder::PARAM_STR)
			));
		}


		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}
}
