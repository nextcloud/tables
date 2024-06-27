<?php

namespace OCA\Tables\Db;

use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Table> */
class TableMapper extends QBMapper {
	protected string $table = 'tables_tables';
	private UserHelper $userHelper;

	public function __construct(IDBConnection $db, UserHelper $userHelper) {
		parent::__construct($db, $this->table, Table::class);
		$this->userHelper = $userHelper;
	}

	/**
	 * @param int $id
	 *
	 * @return Table
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): Table {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @param int $id
	 * @return string
	 * @throws Exception
	 */
	public function findOwnership(int $id): string {
		$qb = $this->db->getQueryBuilder();
		$qb->select('ownership')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $qb->executeQuery()->fetch()['ownership'];
	}

	/**
	 * @param string|null $userId
	 * @return Table[]
	 * @throws Exception
	 */
	public function findAll(?string $userId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table);
		if ($userId !== null && $userId !== '') {
			$qb->where($qb->expr()->eq('ownership', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		}
		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 */
	public function search(?string $term = null, ?string $userId = null, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$shareQueryTablesSharedViaUser = $this->db->getQueryBuilder();
		$shareQueryTablesSharedViaGroup = $this->db->getQueryBuilder();
		$userGroups = $userId ? $this->userHelper->getGroupIdsForUser($userId) : [];

		// get table ids, that are shared with the given user
		// only makes sense if a user is given, otherwise will always get all shares doubled
		if ($userId) {
			$shareQueryTablesSharedViaUser->selectDistinct('node_id')
				->from('tables_shares')
				->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter('table', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter('user', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

			if($userGroups) {
				$shareQueryTablesSharedViaGroup->selectDistinct('node_id')
					->from('tables_shares')
					->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter('table', IQueryBuilder::PARAM_STR)))
					->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter('group', IQueryBuilder::PARAM_STR)))
					->andWhere($qb->expr()->in('receiver', $qb->createNamedParameter($userGroups, IQueryBuilder::PARAM_STR_ARRAY)));
			}
		}

		$qb->select('*')
			->from($this->table);

		if ($userId) {
			$qb->andWhere($qb->expr()->eq('ownership', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
			$qb->orWhere($shareQueryTablesSharedViaUser->expr()->in('id', $qb->createFunction($shareQueryTablesSharedViaUser->getSQL()), IQueryBuilder::PARAM_INT_ARRAY));
			if($userGroups) {
				$qb->orWhere($shareQueryTablesSharedViaGroup->expr()->in('id', $qb->createFunction($shareQueryTablesSharedViaGroup->getSQL()), IQueryBuilder::PARAM_INT_ARRAY));
			}
		}

		if ($term) {
			$qb->andWhere($qb->expr()->iLike(
				'title',
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
