<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<View> */
class ViewMapper extends QBMapper {
	protected string $table = 'tables_views';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, View::class);
	}

	/**
	 * @throws Exception
	 */
	public function find(int $id): View {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws Exception
	 */
	public function findBaseView(?int $tableId = null): View {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table);
		if ($tableId !== null) {
			$qb->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('is_base_view', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		}
		return $this->findEntity($qb);
	}

	/**
	 * @throws Exception
	 */
	public function findAll(?int $tableId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table);
		if ($tableId !== null) {
			$qb->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT)));
		}
		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 */
	public function findAllNotBaseViews(?int $tableId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table);
		if ($tableId !== null) {
			$qb->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('is_base_view', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
		}
		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 */
	public function search(string $term = null, ?string $userId = null, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$shareQuery = $this->db->getQueryBuilder();

		// get view ids, that are shared with the given user
		// only makes sense if a user is given, otherwise will always get all shares doubled
		if ($userId !== null && $userId !== '') {
			$shareQuery->selectDistinct('node_id')
				->from('tables_shares')
				->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter('view', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		}

		$qb->select('*')
			->from($this->table);

		if ($userId !== null && $userId !== '') {
			$qb->andWhere($qb->expr()->eq('created_by', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
			$qb->orWhere($shareQuery->expr()->in('id', $qb->createFunction($shareQuery->getSQL()), IQueryBuilder::PARAM_INT_ARRAY));
		}
		//TODO: Created by instead of ownership?

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

		$sql = $qb->getSQL();

		return $this->findEntities($qb);
	}
}
