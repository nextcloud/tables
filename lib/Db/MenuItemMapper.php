<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<MenuItem> */
class MenuItemMapper extends QBMapper {
	protected string $table = 'tables_contexts_menu_item';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, MenuItem::class);
	}

	/**
	 * @return MenuItem[] ordered by their position in the menu
	 * @throws Exception
	 */
	public function findByContextId(int $contextId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('context_id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)))
			->orderBy('order', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 */
	public function deleteAllByContextId(int $contextId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('context_id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
