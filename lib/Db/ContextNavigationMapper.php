<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<ContextNavigation> */
class ContextNavigationMapper extends QBMapper {
	protected string $table = 'tables_contexts_navigation';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, ContextNavigation::class);
	}

	/**
	 * @throws Exception
	 */
	public function deleteByShareId(int $shareId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('share_id', $qb->createNamedParameter($shareId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function setDisplayModeByShareId(int $shareId, int $displayMode, string $userId): ContextNavigation {
		$entity = new ContextNavigation();
		$entity->setShareId($shareId);
		$entity->setDisplayMode($displayMode);
		$entity->setUserId($userId);

		return $this->insertOrUpdate($entity);
	}
}
