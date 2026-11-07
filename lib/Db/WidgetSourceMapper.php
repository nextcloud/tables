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

/**
 * The table keys on widget_id, so rows are written with plain queries instead of
 * the id based insert and update of QBMapper.
 *
 * @template-extends QBMapper<WidgetSource>
 */
class WidgetSourceMapper extends QBMapper {
	protected string $table = 'tables_contexts_widget_source';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, WidgetSource::class);
	}

	/**
	 * @param int[] $widgetIds
	 * @return array<int, int> node relation ids by widget id
	 * @throws Exception
	 */
	public function findNodeRelIdsByWidgetIds(array $widgetIds): array {
		if ($widgetIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('widget_id', 'node_rel_id')
			->from($this->table)
			->where($qb->expr()->in('widget_id', $qb->createNamedParameter($widgetIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$result = $qb->executeQuery();
		$sources = [];
		while ($row = $result->fetchAssociative()) {
			$sources[(int)$row['widget_id']] = (int)$row['node_rel_id'];
		}
		$result->closeCursor();
		return $sources;
	}

	/**
	 * Points a widget at a node relation, or removes its source when null.
	 *
	 * @throws Exception
	 */
	public function setSource(int $widgetId, ?int $nodeRelId): void {
		$this->deleteByWidgetIds([$widgetId]);
		if ($nodeRelId === null) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->table)
			->values([
				'widget_id' => $qb->createNamedParameter($widgetId, IQueryBuilder::PARAM_INT),
				'node_rel_id' => $qb->createNamedParameter($nodeRelId, IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
	}

	/**
	 * @param int[] $widgetIds
	 * @throws Exception
	 */
	public function deleteByWidgetIds(array $widgetIds): void {
		if ($widgetIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->in('widget_id', $qb->createNamedParameter($widgetIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->executeStatement();
	}

	/**
	 * @param int[] $nodeRelIds
	 * @throws Exception
	 */
	public function deleteByNodeRelIds(array $nodeRelIds): void {
		if ($nodeRelIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->in('node_rel_id', $qb->createNamedParameter($nodeRelIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->executeStatement();
	}
}
