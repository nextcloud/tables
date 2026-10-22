<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCA\Tables\Db\Page;
use OCA\Tables\Validation\TechnicalNameValidator;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Grid pages: a title, icon, description and technical name on pages, the widgets of
 * a page with their settings as one JSON object, and the node a data widget shows.
 *
 * Every existing page gets a technical name: the start page of an application
 * `dashboard`, every other page `page_<id>`.
 */
class Version2400Date20261022000000 extends SimpleMigrationStep {
	private const PREFIX = 'tables_contexts_';
	private const PAGE_TABLE = self::PREFIX . 'page';
	private const PAGE_NAME_INDEX = 'tables_ctx_page_name_uq';
	private const START_PAGE_NAME = 'dashboard';

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->extendPageTable($schema);
		$this->haveWidgetTable($schema);
		$this->haveWidgetSourceTable($schema);

		return $schema;
	}

	private function extendPageTable(ISchemaWrapper $schema): void {
		if (!$schema->hasTable(self::PAGE_TABLE)) {
			return;
		}
		$table = $schema->getTable(self::PAGE_TABLE);
		if (!$table->hasColumn('title')) {
			$table->addColumn('title', Types::STRING, ['notnull' => false, 'length' => 200]);
		}
		if (!$table->hasColumn('icon')) {
			$table->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
		}
		if (!$table->hasColumn('description')) {
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
		}
		if (!$table->hasColumn('technical_name')) {
			$table->addColumn('technical_name', Types::STRING, ['notnull' => false, 'length' => TechnicalNameValidator::MAX_LENGTH]);
		}
		// Also serves lookups by context_id. Safe while existing pages have no name yet:
		// pgsql, mysql and sqlite, the databases Tables supports, treat NULLs in a unique
		// index as distinct.
		if (!$table->hasIndex(self::PAGE_NAME_INDEX)) {
			$table->addUniqueIndex(['context_id', 'technical_name'], self::PAGE_NAME_INDEX);
		}
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$select = $this->db->getQueryBuilder();
		$select->select('id', 'context_id', 'page_type', 'technical_name')
			->from(self::PAGE_TABLE)
			->orderBy('context_id')
			->addOrderBy('id');
		$result = $select->executeQuery();
		$rows = $result->fetchAllAssociative();
		$result->closeCursor();

		$names = self::backfillNames($rows);
		if ($names === []) {
			return;
		}

		$update = $this->db->getQueryBuilder();
		$update->update(self::PAGE_TABLE)
			->set('technical_name', $update->createParameter('technicalName'))
			->where($update->expr()->eq('id', $update->createParameter('pageId')));

		$this->db->beginTransaction();
		try {
			foreach ($names as $pageId => $technicalName) {
				$update->setParameter('technicalName', $technicalName);
				$update->setParameter('pageId', $pageId, IQueryBuilder::PARAM_INT);
				$update->executeStatement();
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * The technical name of every page row that has none, by page id. The start page of
	 * an application gets `dashboard`, unless an earlier page holds it; every other page
	 * gets `page_<id>`.
	 *
	 * @param list<array<string, mixed>> $rows page rows
	 * @return array<int, string>
	 */
	public static function backfillNames(array $rows): array {
		usort($rows, static fn (array $a, array $b): int => [(int)$a['context_id'], (int)$a['id']] <=> [(int)$b['context_id'], (int)$b['id']]);

		$taken = [];
		foreach ($rows as $row) {
			if (is_string($row['technical_name'] ?? null) && $row['technical_name'] !== '') {
				$taken[(int)$row['context_id']][$row['technical_name']] = true;
			}
		}

		$names = [];
		foreach ($rows as $row) {
			if (is_string($row['technical_name'] ?? null) && $row['technical_name'] !== '') {
				continue;
			}
			$contextId = (int)$row['context_id'];
			$technicalName = $row['page_type'] === Page::TYPE_STARTPAGE && !isset($taken[$contextId][self::START_PAGE_NAME])
				? self::START_PAGE_NAME
				: 'page_' . (int)$row['id'];
			$taken[$contextId][$technicalName] = true;
			$names[(int)$row['id']] = $technicalName;
		}
		return $names;
	}

	private function haveWidgetTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable(self::PREFIX . 'widget')) {
			return;
		}
		$table = $schema->createTable(self::PREFIX . 'widget');
		$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('page_id', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('title', Types::STRING, ['notnull' => false, 'length' => 200]);
		$table->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('description', Types::TEXT, ['notnull' => false]);
		$table->addColumn('grid_x', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('grid_y', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('grid_width', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
		$table->addColumn('grid_height', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
		$table->addColumn('settings', Types::TEXT, ['notnull' => false]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['page_id'], 'tables_ctx_widget_page_idx');
	}

	private function haveWidgetSourceTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable(self::PREFIX . 'widget_source')) {
			return;
		}
		$table = $schema->createTable(self::PREFIX . 'widget_source');
		$table->addColumn('widget_id', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('node_rel_id', Types::INTEGER, ['notnull' => true]);
		$table->setPrimaryKey(['widget_id']);
		$table->addIndex(['node_rel_id'], 'tables_ctx_wsource_node_idx');
	}
}
