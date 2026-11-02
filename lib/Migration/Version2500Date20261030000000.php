<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCA\Tables\Db\Page;
use OCA\Tables\Service\GridWidgetTypes;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * The start page of an application is a grid page. Every start page without widgets is
 * filled the way a new one is: a header with the name and description of its application,
 * then one table or view widget per table or view, in the order the start page listed them.
 * A start page that has widgets is left alone, so a second run changes nothing.
 */
class Version2500Date20261030000000 extends SimpleMigrationStep {
	private const PREFIX = 'tables_contexts_';

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		foreach ($this->emptyStartPages() as $startPage) {
			$widgets = GridWidgetTypes::startPageWidgets(
				(string)$startPage['name'],
				(string)($startPage['description'] ?? ''),
				$this->nodeRelIdsInPageOrder((int)$startPage['page_id']),
			);
			$this->db->beginTransaction();
			try {
				foreach ($widgets as $widget) {
					$this->insertWidget((int)$startPage['page_id'], $widget);
				}
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
		}
	}

	/**
	 * @return list<array<string, mixed>> start pages without widgets, with the name and description of their application
	 */
	private function emptyStartPages(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.id as page_id', 'c.name', 'c.description')
			->from(self::PREFIX . 'page', 'p')
			->innerJoin('p', self::PREFIX . 'context', 'c', $qb->expr()->eq('c.id', 'p.context_id'))
			->leftJoin('p', self::PREFIX . 'widget', 'w', $qb->expr()->eq('w.page_id', 'p.id'))
			->where($qb->expr()->eq('p.page_type', $qb->createNamedParameter(Page::TYPE_STARTPAGE)))
			->andWhere($qb->expr()->isNull('w.id'))
			->orderBy('p.id');
		$result = $qb->executeQuery();
		$rows = $result->fetchAllAssociative();
		$result->closeCursor();
		return $rows;
	}

	/**
	 * @return list<int> the tables and views a start page lists, as node relations of its application, in page order
	 */
	private function nodeRelIdsInPageOrder(int $pageId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('pc.node_rel_id')
			->from(self::PREFIX . 'page_content', 'pc')
			->innerJoin('pc', self::PREFIX . 'rel_context_node', 'r', $qb->expr()->eq('r.id', 'pc.node_rel_id'))
			->where($qb->expr()->eq('pc.page_id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)))
			->orderBy('pc.order')
			->addOrderBy('pc.id');
		$result = $qb->executeQuery();
		$nodeRelIds = array_map(intval(...), $result->fetchFirstColumn());
		$result->closeCursor();
		return array_values(array_unique($nodeRelIds));
	}

	/**
	 * @param array{type: string, position: array{gridX: int, gridY: int, gridWidth: int, gridHeight: int}, settings: array<string, mixed>, nodeRelId: ?int} $widget
	 */
	private function insertWidget(int $pageId, array $widget): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::PREFIX . 'widget')
			->values([
				'page_id' => $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT),
				'type' => $qb->createNamedParameter($widget['type']),
				'grid_x' => $qb->createNamedParameter($widget['position']['gridX'], IQueryBuilder::PARAM_INT),
				'grid_y' => $qb->createNamedParameter($widget['position']['gridY'], IQueryBuilder::PARAM_INT),
				'grid_width' => $qb->createNamedParameter($widget['position']['gridWidth'], IQueryBuilder::PARAM_INT),
				'grid_height' => $qb->createNamedParameter($widget['position']['gridHeight'], IQueryBuilder::PARAM_INT),
				'settings' => $qb->createNamedParameter($widget['settings'], IQueryBuilder::PARAM_JSON),
			])
			->executeStatement();
		if ($widget['nodeRelId'] === null) {
			return;
		}
		$widgetId = $qb->getLastInsertId();
		$source = $this->db->getQueryBuilder();
		$source->insert(self::PREFIX . 'widget_source')
			->values([
				'widget_id' => $source->createNamedParameter($widgetId, IQueryBuilder::PARAM_INT),
				'node_rel_id' => $source->createNamedParameter($widget['nodeRelId'], IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
	}
}
