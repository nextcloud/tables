<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\TTransactional;
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
	use TTransactional;

	private const DB_CHUNK_SIZE = 1_000;

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
			->where($qb->expr()->in('widget_id', $qb->createParameter('widgetIds')));

		$sources = [];
		foreach (array_chunk($widgetIds, self::DB_CHUNK_SIZE) as $chunk) {
			$qb->setParameter('widgetIds', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$result = $qb->executeQuery();
			while ($row = $result->fetchAssociative()) {
				$sources[(int)$row['widget_id']] = (int)$row['node_rel_id'];
			}
			$result->closeCursor();
		}
		return $sources;
	}

	/**
	 * The given node relations that belong to the application of a page.
	 *
	 * @param int[] $nodeRelIds
	 * @return int[]
	 * @throws Exception
	 */
	public function filterNodeRelIdsOfPageContext(int $pageId, array $nodeRelIds): array {
		if ($nodeRelIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('r.id')
			->from('tables_contexts_rel_context_node', 'r')
			->innerJoin('r', 'tables_contexts_page', 'p', $qb->expr()->eq('p.context_id', 'r.context_id'))
			->where($qb->expr()->eq('p.id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('r.id', $qb->createNamedParameter($nodeRelIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$result = $qb->executeQuery();
		$ids = [];
		while ($row = $result->fetchAssociative()) {
			$ids[] = (int)$row['id'];
		}
		$result->closeCursor();
		return $ids;
	}

	/**
	 * Points a widget at a node relation, or removes its source when null.
	 *
	 * @throws Exception
	 */
	public function setSource(int $widgetId, ?int $nodeRelId): void {
		$this->atomic(function () use ($widgetId, $nodeRelId): void {
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
		}, $this->db);
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
			->where($qb->expr()->in('widget_id', $qb->createParameter('widgetIds')));
		foreach (array_chunk($widgetIds, self::DB_CHUNK_SIZE) as $chunk) {
			$qb->setParameter('widgetIds', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$qb->executeStatement();
		}
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
			->where($qb->expr()->in('node_rel_id', $qb->createParameter('nodeRelIds')));
		foreach (array_chunk($nodeRelIds, self::DB_CHUNK_SIZE) as $chunk) {
			$qb->setParameter('nodeRelIds', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$qb->executeStatement();
		}
	}
}
