<?php

declare(strict_types=1);

namespace OCA\Tables\Db;

use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Context> */
class ContextMapper extends QBMapper {
	protected string $table = 'tables_contexts_context';
	private UserHelper $userHelper;

	public function __construct(IDBConnection $db, UserHelper $userHelper) {
		$this->userHelper = $userHelper;
		parent::__construct($db, $this->table, Context::class);
	}
	/**
	 * @return Context[]
	 * @throws Exception
	 */
	public function findAll(?string $userId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('c.*')
			->from($this->table, 'c');
		if ($userId !== null) {
			$this->applyOwnedOrSharedQuery($qb, $userId);
		}

		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findById(int $contextId, ?string $userId = null): Context {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
			'c.*',
			'r.id as node_rel_id', 'r.node_id', 'r.node_type', 'r.permissions',
			'p.page_type',
			'pc.id as content_id', 'pc.page_id', 'pc.order',
			'n.display_mode as display_mode_default',
			's.id as share_id', 's.receiver', 's.receiver_type'
		)
			->from($this->table, 'c')
			->where($qb->expr()->eq('c.id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)));

		if ($userId !== null) {
			$this->applyOwnedOrSharedQuery($qb, $userId);
			$qb->addSelect('n2.display_mode');
			$qb->leftJoin('s', 'tables_contexts_navigation', 'n2', $qb->expr()->andX(
				$qb->expr()->eq('s.id', 'n2.share_id'),
				$qb->expr()->eq('n2.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
			));
		} else {
			$qb->leftJoin('c', 'tables_shares', 's', $qb->expr()->andX(
				$qb->expr()->eq('c.id', 's.node_id'),
				$qb->expr()->eq('s.node_type', $qb->createNamedParameter('context')),
			));
		}

		$qb->leftJoin('c', 'tables_contexts_page', 'p', $qb->expr()->eq('c.id', 'p.context_id'));
		$qb->leftJoin('p', 'tables_contexts_page_content', 'pc', $qb->expr()->eq('p.id', 'pc.page_id'));
		$qb->leftJoin('c', 'tables_contexts_rel_context_node', 'r', $qb->expr()->eq('c.id', 'r.context_id'));
		$qb->leftJoin('s', 'tables_contexts_navigation', 'n', $qb->expr()->andX(
			$qb->expr()->eq('s.id', 'n.share_id'),
			$qb->expr()->eq('n.user_id', $qb->createNamedParameter('')),
		));

		$qb->andWhere($qb->expr()->orX(
			$qb->expr()->eq('pc.node_rel_id', 'r.id'),
			$qb->expr()->isNull('pc.node_rel_id'),
		));

		$qb->orderBy('pc.order', 'ASC');

		$result = $qb->executeQuery();
		$r = $result->fetchAll();

		$formatted = [
			'id' => $r[0]['id'],
			'name' => $r[0]['name'],
			'icon' => $r[0]['icon'],
			'description' => $r[0]['description'],
			'owner_id' => $r[0]['owner_id'],
			'owner_type' => $r[0]['owner_type'],
		];

		$formatted['sharing'] = array_reduce($r, function (array $carry, array $item) use ($userId) {
			if ($item['share_id'] === null) {
				// empty Context
				return $carry;
			}
			$carry[$item['share_id']] = [
				'share_id' => $item['share_id'],
				'receiver' => $item['receiver'],
				'receiver_type' => $item['receiver_type'],
				'display_mode_default' => $item['display_mode_default'],
			];
			if ($userId !== null) {
				$carry[$item['share_id']]['display_mode'] = $item['display_mode'];
			}
			return $carry;
		}, []);

		$formatted['nodes'] = array_reduce($r, function (array $carry, array $item) {
			if ($item['node_rel_id'] === null) {
				// empty Context
				return $carry;
			}
			$carry[$item['node_rel_id']] = [
				'id' => $item['node_rel_id'],
				'node_id' => $item['node_id'],
				'node_type' => $item['node_type'],
				'permissions' => $item['permissions'],
			];
			return $carry;
		}, []);

		$formatted['pages'] = array_reduce($r, function (array $carry, array $item) {
			if ($item['page_id'] === null) {
				// empty Context
				return $carry;
			}
			if (!isset($carry[$item['page_id']])) {
				$carry[$item['page_id']] = ['content' => []];
			}
			$carry[$item['page_id']]['id'] = $item['page_id'];
			$carry[$item['page_id']]['page_type'] = $item['page_type'];
			$carry[$item['page_id']]['content'][$item['content_id']] = [
				'order' => $item['order'],
				'node_rel_id' => $item['node_rel_id']
			];

			return $carry;
		}, []);

		return $this->mapRowToEntity($formatted);
	}

	protected function applyOwnedOrSharedQuery(IQueryBuilder $qb, string $userId): void {
		$sharedToConditions = $qb->expr()->orX();

		// shared to user clause
		$userShare = $qb->expr()->andX(
			$qb->expr()->eq('s.receiver_type', $qb->createNamedParameter('user')),
			$qb->expr()->eq('s.receiver', $qb->createNamedParameter($userId)),
		);
		$sharedToConditions->add($userShare);

		// shared to group clause
		$groupIDs = $this->userHelper->getGroupIdsForUser($userId);
		if (!empty($groupIDs)) {
			$groupShares = $qb->expr()->andX(
				$qb->expr()->eq('s.receiver_type', $qb->createNamedParameter('group')),
				$qb->expr()->in('s.receiver', $qb->createNamedParameter($groupIDs, IQueryBuilder::PARAM_STR_ARRAY)),
			);
			$sharedToConditions->add($groupShares);
		}

		// owned contexts + apply share conditions
		$qb->leftJoin('c', 'tables_shares', 's', $qb->expr()->andX(
			$qb->expr()->eq('c.id', 's.node_id'),
			$qb->expr()->eq('s.node_type', $qb->createNamedParameter('context')),
			$sharedToConditions,
		));

		$whereExpression = $qb->expr()->orX(
			$qb->expr()->eq('owner_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
			$qb->expr()->isNotNull('s.receiver'),
		);
		if ($qb->getQueryPart('where') === null) {
			$qb->where($whereExpression);
		} else {
			$qb->andWhere($whereExpression);
		}
	}
}
