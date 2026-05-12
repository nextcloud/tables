<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class FormattingRuleColMapper {
	private string $table = 'tables_fmt_rule_cols';

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * Replace all column entries for a rule with the given set.
	 *
	 * @param int[] $columnIds
	 * @throws Exception
	 */
	public function syncForRule(string $ruleId, int $viewId, array $columnIds): void {
		$this->deleteByRule($ruleId);

		foreach ($columnIds as $columnId) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert($this->table)
				->values([
					'rule_id' => $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_STR),
					'view_id' => $qb->createNamedParameter($viewId, IQueryBuilder::PARAM_INT),
					'column_id' => $qb->createNamedParameter($columnId, IQueryBuilder::PARAM_INT),
				])
				->executeStatement();
		}
	}

	/**
	 * @return list<array{rule_id: string, view_id: int}>
	 * @throws Exception
	 */
	public function findRuleIdsByColumn(int $columnId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('rule_id', 'view_id')
			->from($this->table)
			->where($qb->expr()->eq('column_id', $qb->createNamedParameter($columnId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$rows = [];
		while ($row = $result->fetch()) {
			$rows[] = ['rule_id' => (string)$row['rule_id'], 'view_id' => (int)$row['view_id']];
		}
		$result->closeCursor();
		return $rows;
	}

	/** @throws Exception */
	public function deleteByColumn(int $columnId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('column_id', $qb->createNamedParameter($columnId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** @throws Exception */
	public function deleteByView(int $viewId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('view_id', $qb->createNamedParameter($viewId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** @throws Exception */
	public function deleteByRule(string $ruleId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('rule_id', $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}
}
