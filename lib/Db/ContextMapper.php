<?php

declare(strict_types=1);

namespace OCA\Tables\Db;

use OCA\Tables\Helper\UserHelper;
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

			$qb->where($qb->expr()->orX(
				$qb->expr()->eq('owner_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->isNotNull('s.receiver'),
			));
		}

		return $this->findEntities($qb);
	}
}
