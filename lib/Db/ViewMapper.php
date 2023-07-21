<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<View> */
class ViewMapper extends QBMapper {
	protected string $table = 'tables_views';

	protected TableMapper $tableMapper;

	public function __construct(IDBConnection $db, TableMapper $tableMapper) {
		parent::__construct($db, $this->table, View::class);
		$this->tableMapper = $tableMapper;
	}


	/**
	 * @param int $id
	 * @return View
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id, bool $skipEnhancement = false): View {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$view = $this->findEntity($qb);
		if(!$skipEnhancement) $this->enhanceByOwnership($view);
		return $view;
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
		$view = $this->findEntity($qb);
		$this->enhanceByOwnership($view);
		return $view;
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
		$views = $this->findEntities($qb);
		foreach($views as $view) {
			$this->enhanceByOwnership($view);
		}
		return $views;
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
		$views = $this->findEntities($qb);
		foreach($views as $view) {
			$this->enhanceByOwnership($view);
		}
		return $views;
	}

	/**
	 * @throws Exception
	 */
	public function search(string $term = null, ?string $userId = null, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$shareViewQuery = $this->db->getQueryBuilder();
		$shareTableQuery = $this->db->getQueryBuilder();

		// get view ids, that are shared with the given user
		// only makes sense if a user is given, otherwise will always get all shares doubled
		if ($userId !== null && $userId !== '') {
			$shareViewQuery->selectDistinct('node_id')
				->from('tables_shares')
				->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter('view', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
			$shareTableQuery->selectDistinct('node_id')
				->from('tables_shares')
				->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter('table', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		}

		$qb->select('v.*')
			->from($this->table, 'v')
			->leftJoin('v','tables_tables', 't','t.id = v.table_id');

		if ($userId !== null && $userId !== '') {
			$qb->andWhere($qb->expr()->eq('ownership', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orWhere($shareViewQuery->expr()->in('v.id', $qb->createFunction($shareViewQuery->getSQL()), IQueryBuilder::PARAM_INT_ARRAY))
			->orWhere($shareTableQuery->expr()->in('v.table_id', $qb->createFunction($shareTableQuery->getSQL()), IQueryBuilder::PARAM_INT_ARRAY));
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

		$sql = $qb->getSQL();

		$views = $this->findEntities($qb);
		foreach($views as $view) {
			$this->enhanceByOwnership($view);
		}
		return $views;
	}

	private function enhanceByOwnership(View &$view): void {
		$view->setOwnership($this->tableMapper->findOwnership($view->getTableId()));
		$view->resetUpdatedFields();
	}
}
