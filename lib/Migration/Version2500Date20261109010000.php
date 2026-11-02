<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Fills every start page without widgets: a header with the name and description of its
 * application, then one table or view widget per table or view, in the order the start
 * page listed them. A start page that has widgets is left alone, so a second run changes
 * nothing.
 *
 * The layout is copied here, not taken from GridWidgetTypes, so this step keeps writing
 * what it wrote when it shipped, whatever later versions do with new start pages.
 */
class Version2500Date20261109010000 extends SimpleMigrationStep {
	private const PREFIX = 'tables_contexts_';
	private const COLUMNS = 12;
	private const HEADER_HEIGHT = 2;
	private const DATA_HEIGHT = 5;
	private const MAX_WIDGETS = 100;
	private const HEADING_MAX_LENGTH = 200;
	private const SUBHEADING_MAX_LENGTH = 2000;
	private const PAGES_PER_TRANSACTION = 100;

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$startPages = $this->emptyStartPages();
		if ($startPages === []) {
			return;
		}

		$widget = $this->db->getQueryBuilder();
		$widget->insert(self::PREFIX . 'widget')
			->values([
				'page_id' => $widget->createParameter('pageId'),
				'type' => $widget->createParameter('type'),
				'grid_x' => $widget->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'grid_y' => $widget->createParameter('gridY'),
				'grid_width' => $widget->createNamedParameter(self::COLUMNS, IQueryBuilder::PARAM_INT),
				'grid_height' => $widget->createParameter('gridHeight'),
				'settings' => $widget->createParameter('settings'),
			]);
		$source = $this->db->getQueryBuilder();
		$source->insert(self::PREFIX . 'widget_source')
			->values([
				'widget_id' => $source->createParameter('widgetId'),
				'node_rel_id' => $source->createParameter('nodeRelId'),
			]);

		$output->startProgress(count($startPages));
		foreach (array_chunk($startPages, self::PAGES_PER_TRANSACTION, true) as $chunk) {
			$this->db->beginTransaction();
			try {
				foreach ($chunk as $pageId => $startPage) {
					$this->fill($widget, $source, $pageId, $startPage);
					$output->advance();
				}
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
		}
		$output->finishProgress();
	}

	/**
	 * @param array{name: string, description: string, nodeRelIds: list<int>} $startPage
	 */
	private function fill(IQueryBuilder $widget, IQueryBuilder $source, int $pageId, array $startPage): void {
		$gridY = 0;
		$heading = trim($startPage['name']);
		if ($heading !== '') {
			$settings = [
				'heading' => mb_substr($heading, 0, self::HEADING_MAX_LENGTH),
				'subheading' => mb_substr(trim($startPage['description']), 0, self::SUBHEADING_MAX_LENGTH),
				'textAlign' => 'start',
				'backgroundColor' => '',
				'textColor' => '',
			];
			$this->insertWidget($widget, $source, $pageId, 'header', $gridY, self::HEADER_HEIGHT, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), null);
			$gridY += self::HEADER_HEIGHT;
		}
		$room = self::MAX_WIDGETS - ($heading !== '' ? 1 : 0);
		foreach (array_slice($startPage['nodeRelIds'], 0, $room) as $nodeRelId) {
			$this->insertWidget($widget, $source, $pageId, 'data', $gridY, self::DATA_HEIGHT, '{}', $nodeRelId);
			$gridY += self::DATA_HEIGHT;
		}
	}

	private function insertWidget(IQueryBuilder $widget, IQueryBuilder $source, int $pageId, string $type, int $gridY, int $gridHeight, string $settings, ?int $nodeRelId): void {
		$widget->setParameter('pageId', $pageId, IQueryBuilder::PARAM_INT)
			->setParameter('type', $type)
			->setParameter('gridY', $gridY, IQueryBuilder::PARAM_INT)
			->setParameter('gridHeight', $gridHeight, IQueryBuilder::PARAM_INT)
			->setParameter('settings', $settings)
			->executeStatement();
		if ($nodeRelId === null) {
			return;
		}
		$source->setParameter('widgetId', $widget->getLastInsertId(), IQueryBuilder::PARAM_INT)
			->setParameter('nodeRelId', $nodeRelId, IQueryBuilder::PARAM_INT)
			->executeStatement();
	}

	/**
	 * The start pages without widgets, with the name and description of their application
	 * and the node relations they list, in page order. Two queries for all of them.
	 *
	 * @return array<int, array{name: string, description: string, nodeRelIds: list<int>}> by page id
	 */
	private function emptyStartPages(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.id', 'c.name', 'c.description')
			->from(self::PREFIX . 'page', 'p')
			->innerJoin('p', self::PREFIX . 'context', 'c', $qb->expr()->eq('c.id', 'p.context_id'))
			->leftJoin('p', self::PREFIX . 'widget', 'w', $qb->expr()->eq('w.page_id', 'p.id'))
			->where($qb->expr()->eq('p.page_type', $qb->createNamedParameter('startpage')))
			->andWhere($qb->expr()->isNull('w.id'))
			->orderBy('p.id');
		$result = $qb->executeQuery();
		$startPages = [];
		while ($row = $result->fetchAssociative()) {
			$startPages[(int)$row['id']] = ['name' => (string)$row['name'], 'description' => (string)($row['description'] ?? ''), 'nodeRelIds' => []];
		}
		$result->closeCursor();
		if ($startPages === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('pc.page_id', 'pc.node_rel_id')
			->from(self::PREFIX . 'page_content', 'pc')
			->innerJoin('pc', self::PREFIX . 'rel_context_node', 'r', $qb->expr()->eq('r.id', 'pc.node_rel_id'))
			->innerJoin('pc', self::PREFIX . 'page', 'p', $qb->expr()->eq('p.id', 'pc.page_id'))
			->where($qb->expr()->eq('p.page_type', $qb->createNamedParameter('startpage')))
			->orderBy('pc.page_id')
			->addOrderBy('pc.order')
			->addOrderBy('pc.id');
		$result = $qb->executeQuery();
		while ($row = $result->fetchAssociative()) {
			$pageId = (int)$row['page_id'];
			$nodeRelId = (int)$row['node_rel_id'];
			if (isset($startPages[$pageId]) && !in_array($nodeRelId, $startPages[$pageId]['nodeRelIds'], true)) {
				$startPages[$pageId]['nodeRelIds'][] = $nodeRelId;
			}
		}
		$result->closeCursor();
		return $startPages;
	}
}
