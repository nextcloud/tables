<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Db;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Context> */
class ContextMapper extends QBMapper {
	protected string $table = 'tables_contexts_context';

	public function __construct(
		IDBConnection $db,
		protected UserHelper $userHelper,
		protected GroupHelper $groupHelper,
	) {
		parent::__construct($db, $this->table, Context::class);
	}

	protected function getFindContextBaseQuery(?string $userId): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
			'c.*',
			'r.id as node_rel_id', 'r.node_id', 'r.node_type', 'r.permissions',
			'p.id as page_id', 'p.page_type',
			'pc.id as content_id', 'pc.order',
			'n.display_mode as display_mode_default',
			's.id as share_id', 's.receiver', 's.receiver_type'
		)
			->from($this->table, 'c');

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

		return $qb;
	}

	protected function formatResultRows(array $rows, ?string $userId) {
		$formatted = [
			'id' => $rows[0]['id'],
			'name' => $rows[0]['name'],
			'icon' => $rows[0]['icon'],
			'description' => $rows[0]['description'],
			'owner_id' => $rows[0]['owner_id'],
			'owner_type' => $rows[0]['owner_type'],
		];

		$formatted['sharing'] = array_reduce($rows, function (array $carry, array $item) use ($userId) {
			if ($item['share_id'] === null) {
				// empty Context
				return $carry;
			}
			$carry[$item['share_id']] = [
				'share_id' => (int)$item['share_id'],
				'receiver' => $item['receiver'],
				'receiver_type' => $item['receiver_type'],
				'receiver_display_name' => match ($item['receiver_type']) {
					'user' => $this->userHelper->getUserDisplayName($item['receiver']),
					'group' => $this->groupHelper->getGroupDisplayName($item['receiver']),
					default => $item['receiver'],
				},
				'display_mode_default' => (int)$item['display_mode_default'],
			];
			if ($userId !== null) {
				if ($item['display_mode'] === null) {
					$item['display_mode'] = $item['display_mode_default'];
				}
				$carry[$item['share_id']]['display_mode'] = (int)$item['display_mode'];
			}
			return $carry;
		}, []);

		$formatted['nodes'] = array_reduce($rows, function (array $carry, array $item) {
			if ($item['node_rel_id'] === null) {
				// empty Context
				return $carry;
			}
			$carry[$item['node_rel_id']] = [
				'id' => (int)$item['node_rel_id'],
				'node_id' => (int)$item['node_id'],
				'node_type' => (int)$item['node_type'],
				'permissions' => (int)$item['permissions'],
			];
			return $carry;
		}, []);

		$formatted['pages'] = array_reduce($rows, function (array $carry, array $item) {
			if ($item['page_id'] === null) {
				// empty Context
				return $carry;
			}
			if (!isset($carry[$item['page_id']])) {
				$carry[$item['page_id']] = ['content' => []];
			}
			$carry[$item['page_id']]['id'] = (int)$item['page_id'];
			$carry[$item['page_id']]['page_type'] = $item['page_type'];
			if ($item['node_rel_id'] !== null) {
				$carry[$item['page_id']]['content'][$item['content_id']] = [
					'order' => (int)$item['order'],
					'node_rel_id' => (int)$item['node_rel_id']
				];
			}

			return $carry;
		}, []);

		return $this->mapRowToEntity($formatted);
	}

	/**
	 * @return Context[]
	 * @throws Exception
	 */
	public function findAll(?string $userId = null): array {
		$qb = $this->getFindContextBaseQuery($userId);

		$result = $qb->executeQuery();
		$r = $result->fetchAll();

		$contextIds = [];
		foreach ($r as $row) {
			$contextIds[$row['id']] = 1;
		}
		$contextIds = array_keys($contextIds);
		unset($row);

		$resultEntities = [];
		foreach ($contextIds as $contextId) {
			$workArray = [];
			foreach ($r as $row) {
				if ((int)$row['id'] === $contextId) {
					$workArray[] = $row;
				}
			}
			$resultEntities[] = $this->formatResultRows($workArray, $userId);
		}

		return $resultEntities;
	}
	public function findForNavBar(string $userId): array {
		$qb = $this->getFindContextBaseQuery($userId);
		$groupIDs = $this->userHelper->getGroupIdsForUser($userId);
		$qb->andWhere($qb->expr()->orX(
			// default
			$qb->expr()->andX(
				// requires lack of user overwrite, indicated by n2.display_mode
				$qb->expr()->isNull('n2.display_mode'),
				// requires a display mode also depending on the role…
				$qb->expr()->orX(
					// not an owner: requires (RECIPIENT or) ALL
					$qb->expr()->andX(
						// groups are not considered, yet
						$qb->expr()->neq('c.owner_id', $qb->createNamedParameter($userId)),
						$qb->expr()->gt('n.display_mode', $qb->createNamedParameter(Application::NAV_ENTRY_MODE_HIDDEN, IQueryBuilder::PARAM_INT)),
					),
					$qb->expr()->andX(
						$qb->expr()->eq('s.receiver_type', $qb->createNamedParameter('group')),
						$qb->expr()->in('s.receiver', $qb->createNamedParameter($groupIDs, IQueryBuilder::PARAM_STR_ARRAY)),
						$qb->expr()->gt('n.display_mode', $qb->createNamedParameter(Application::NAV_ENTRY_MODE_HIDDEN, IQueryBuilder::PARAM_INT)),
					)
				),
			),
			// user override
			$qb->expr()->gt('n2.display_mode', $qb->createNamedParameter(Application::NAV_ENTRY_MODE_HIDDEN, IQueryBuilder::PARAM_INT)),
		));

		$result = $qb->executeQuery();
		$r = $result->fetchAll();

		$contextIds = [];
		foreach ($r as $row) {
			$contextIds[$row['id']] = 1;
		}
		$contextIds = array_keys($contextIds);
		unset($row);

		$resultEntities = [];
		foreach ($contextIds as $contextId) {
			$workArray = [];
			foreach ($r as $row) {
				if ((int)$row['id'] === $contextId) {
					$workArray[] = $row;
				}
			}
			$resultEntities[] = $this->formatResultRows($workArray, $userId);
		}

		return $resultEntities;
	}

	/**
	 * @throws Exception
	 * @throws NotFoundError
	 */
	public function findById(int $contextId, ?string $userId = null): Context {
		$qb = $this->getFindContextBaseQuery($userId);
		$qb->andWhere($qb->expr()->eq('c.id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$r = $result->fetchAll();

		if (empty($r)) {
			throw new NotFoundError('Context does not exist');
		}

		return $this->formatResultRows($r, $userId);
	}

	/**
	 * @return Context[]
	 * @throws Exception
	 */
	public function findAllContainingNode(int $nodeType, int $nodeId, string $userId): array {
		$qb = $this->getFindContextBaseQuery($userId);

		$qb->andWhere($qb->expr()->eq('r.node_id', $qb->createNamedParameter($nodeId)))
			->andWhere($qb->expr()->eq('r.node_type', $qb->createNamedParameter($nodeType)));

		$result = $qb->executeQuery();
		$r = $result->fetchAll();

		$contextIds = [];
		foreach ($r as $row) {
			$contextIds[$row['id']] = 1;
		}
		$contextIds = array_keys($contextIds);
		unset($row);

		$resultEntities = [];
		foreach ($contextIds as $contextId) {
			$workArray = [];
			foreach ($r as $row) {
				if ($row['id'] === $contextId) {
					$workArray[] = $row;
				}
			}
			$resultEntities[] = $this->formatResultRows($workArray, $userId);
		}

		return $resultEntities;
	}

	protected function applyOwnedOrSharedQuery(IQueryBuilder $qb, string $userId): void {
		$sharedToConditions = $qb->expr()->orX();

		// shared by user clause
		$userInitiatedShare = $qb->expr()->eq('s.sender', $qb->createNamedParameter($userId));
		$sharedToConditions->add($userInitiatedShare);

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
