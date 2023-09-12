<?php

namespace OCA\Tables\Db;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<View> */
class ViewMapper extends QBMapper {
	protected string $table = 'tables_views';
	private UserHelper $userHelper;
	private TableMapper $tableMapper;

	public function __construct(IDBConnection $db, TableMapper $tableMapper, UserHelper $userHelper) {
		parent::__construct($db, $this->table, View::class);
		$this->tableMapper = $tableMapper;
		$this->userHelper = $userHelper;
	}


	/**
	 * @param int $id
	 * @param bool $skipEnhancement
	 * @return View
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id, bool $skipEnhancement = false): View {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$view = $this->findEntity($qb);
		if(!$skipEnhancement) {
			$this->enhanceOwnership($view);
		}
		return $view;
	}

	/**
	 * @param int|null $tableId
	 * @return array
	 * @throws Exception
	 * @throws InternalError
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
			$this->enhanceOwnership($view);
		}
		return $views;
	}

	/**
	 * @param string|null $term
	 * @param string|null $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws Exception
	 * @throws InternalError
	 */
	public function search(string $term = null, ?string $userId = null, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$shareTableQuery = $this->db->getQueryBuilder();
		$shareQueryViewsSharedViaUser = $this->db->getQueryBuilder();
		$shareQueryViewsSharedViaGroup = $this->db->getQueryBuilder();
		$userGroups = $this->userHelper->getGroupIdsForUser($userId);

		// get view ids, that are shared with the given user
		// only makes sense if a user is given, otherwise will always get all shares doubled
		if ($userId) {
			$shareQueryViewsSharedViaUser->selectDistinct('node_id')
				->from('tables_shares')
				->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter('view', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter('user', IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

			if($userGroups) {
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

		$qb->select('v.*')
			->from($this->table, 'v')
			->leftJoin('v', 'tables_tables', 't', 't.id = v.table_id');

		if ($userId) {
			$qb->where($qb->expr()->eq('ownership', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orWhere($shareQueryViewsSharedViaUser->expr()->in('v.id', $qb->createFunction($shareQueryViewsSharedViaUser->getSQL()), IQueryBuilder::PARAM_INT_ARRAY));
			if($userGroups) {
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

		$views = $this->findEntities($qb);
		foreach($views as $view) {
			$this->enhanceOwnership($view);
		}
		return $views;
	}

	/**
	 * @param View $view
	 * @return void
	 * @throws InternalError
	 */
	private function enhanceOwnership(View $view): void {
		try {
			$view->setOwnership($this->tableMapper->findOwnership($view->getTableId()));
		} catch (Exception $e) {
			throw new InternalError('Could not find ownership of table');
		}
		$view->resetUpdatedFields();
	}
}
