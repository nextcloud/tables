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
 * Gives every start page without widgets one full width widget that lists the tables
 * and views of its application. A start page that has widgets is left alone, so a
 * second run changes nothing.
 *
 * The layout is copied here, not taken from GridWidgetTypes, so this step keeps writing
 * what it wrote when it shipped, whatever later versions do with new start pages.
 */
class Version2500Date20261109010000 extends SimpleMigrationStep {
	private const PREFIX = 'tables_contexts_';
	private const COLUMNS = 12;
	private const TABLES_HEIGHT = 8;
	private const PAGES_PER_TRANSACTION = 100;

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$pageIds = $this->emptyStartPageIds();
		if ($pageIds === []) {
			return;
		}

		$widget = $this->db->getQueryBuilder();
		$widget->insert(self::PREFIX . 'widget')
			->values([
				'page_id' => $widget->createParameter('pageId'),
				'type' => $widget->createNamedParameter('tables'),
				'grid_x' => $widget->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'grid_y' => $widget->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'grid_width' => $widget->createNamedParameter(self::COLUMNS, IQueryBuilder::PARAM_INT),
				'grid_height' => $widget->createNamedParameter(self::TABLES_HEIGHT, IQueryBuilder::PARAM_INT),
				'settings' => $widget->createNamedParameter('{}'),
			]);

		$output->startProgress(count($pageIds));
		foreach (array_chunk($pageIds, self::PAGES_PER_TRANSACTION) as $chunk) {
			$this->db->beginTransaction();
			try {
				foreach ($chunk as $pageId) {
					$widget->setParameter('pageId', $pageId, IQueryBuilder::PARAM_INT)->executeStatement();
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
	 * @return list<int> the ids of the start pages without widgets
	 */
	private function emptyStartPageIds(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.id')
			->from(self::PREFIX . 'page', 'p')
			->leftJoin('p', self::PREFIX . 'widget', 'w', $qb->expr()->eq('w.page_id', 'p.id'))
			->where($qb->expr()->eq('p.page_type', $qb->createNamedParameter('startpage')))
			->andWhere($qb->expr()->isNull('w.id'))
			->orderBy('p.id');
		$result = $qb->executeQuery();
		$pageIds = [];
		while ($row = $result->fetchAssociative()) {
			$pageIds[] = (int)$row['id'];
		}
		$result->closeCursor();
		return $pageIds;
	}
}
