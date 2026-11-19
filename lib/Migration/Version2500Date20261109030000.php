<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * The menu of an application. An item opens a page, a table or view of the
 * application, or a link; an item that opens nothing is a group holding other items.
 *
 * Every existing application gets a menu of one item, without a title, opening its
 * start page.
 */
class Version2500Date20261109030000 extends SimpleMigrationStep {
	private const TABLE = 'tables_contexts_menu_item';
	private const PAGE_TABLE = 'tables_contexts_page';

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable(self::TABLE)) {
			return null;
		}

		$table = $schema->createTable(self::TABLE);
		$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('context_id', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('parent_id', Types::INTEGER, ['notnull' => false]);
		$table->addColumn('order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('title', Types::STRING, ['notnull' => false, 'length' => 200]);
		$table->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('description', Types::TEXT, ['notnull' => false]);
		$table->addColumn('page_id', Types::INTEGER, ['notnull' => false]);
		$table->addColumn('node_rel_id', Types::INTEGER, ['notnull' => false]);
		$table->addColumn('url', Types::STRING, ['notnull' => false, 'length' => 2000]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['context_id'], 'tables_ctx_menu_ctx_idx');
		$table->addIndex(['page_id'], 'tables_ctx_menu_page_idx');
		$table->addIndex(['node_rel_id'], 'tables_ctx_menu_rel_idx');

		return $schema;
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$select = $this->db->getQueryBuilder();
		$select->select('context_id')
			->selectAlias($select->func()->min('id'), 'page_id')
			->from(self::PAGE_TABLE)
			->where($select->expr()->eq('page_type', $select->createNamedParameter('startpage')))
			->groupBy('context_id');
		$result = $select->executeQuery();
		$startPages = [];
		while ($row = $result->fetchAssociative()) {
			$startPages[(int)$row['context_id']] = (int)$row['page_id'];
		}
		$result->closeCursor();

		$select = $this->db->getQueryBuilder();
		$select->selectDistinct('context_id')->from(self::TABLE);
		$result = $select->executeQuery();
		while (($contextId = $result->fetchOne()) !== false) {
			unset($startPages[(int)$contextId]);
		}
		$result->closeCursor();
		if ($startPages === []) {
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert(self::TABLE)
			->values([
				'context_id' => $insert->createParameter('contextId'),
				'order' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'page_id' => $insert->createParameter('pageId'),
			]);

		$this->db->beginTransaction();
		try {
			foreach ($startPages as $contextId => $pageId) {
				$insert->setParameter('contextId', $contextId, IQueryBuilder::PARAM_INT);
				$insert->setParameter('pageId', $pageId, IQueryBuilder::PARAM_INT);
				$insert->executeStatement();
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
