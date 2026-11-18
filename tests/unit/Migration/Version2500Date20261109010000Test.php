<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Migration;

use OC\DB\SchemaWrapper;
use OC\Migration\NullOutput;
use OCA\Tables\Migration\Version2500Date20261109010000;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Filling the start pages of existing applications, against the database.
 */
class Version2500Date20261109010000Test extends DatabaseTestCase {
	private Version2500Date20261109010000 $migration;

	protected function setUp(): void {
		parent::setUp();
		// the step fills every empty start page it finds; rolling back keeps the instance as it was
		$this->connectionAdapter->beginTransaction();
		$this->migration = new Version2500Date20261109010000($this->connectionAdapter);
	}

	protected function tearDown(): void {
		$this->connectionAdapter->rollBack();
		parent::tearDown();
	}

	public function testAnEmptyStartPageGetsATablesWidgetAndASecondRunChangesNothing(): void {
		$contextId = $this->insertContext('Intake portal', 'Requests from residents');
		$startPage = $this->insert('page', ['context_id' => $contextId, 'page_type' => 'startpage']);
		$gridPage = $this->insert('page', ['context_id' => $contextId, 'page_type' => 'grid']);
		$tasks = $this->insert('rel_context_node', ['context_id' => $contextId, 'node_type' => 0, 'node_id' => 11, 'permissions' => 1]);
		$this->insert('page_content', ['page_id' => $startPage, 'node_rel_id' => $tasks, 'order' => 10]);

		$this->migrate();

		$expected = [['tables', 0, 0, 12, 8, [], null]];
		$this->assertSame($expected, $this->widgets($startPage));
		$this->assertSame([], $this->widgets($gridPage));

		$this->migrate();
		$this->assertSame($expected, $this->widgets($startPage));
	}

	public function testAStartPageWithWidgetsIsLeftAlone(): void {
		$contextId = $this->insertContext('Arranged', '');
		$startPage = $this->insert('page', ['context_id' => $contextId, 'page_type' => 'startpage']);
		$node = $this->insert('rel_context_node', ['context_id' => $contextId, 'node_type' => 0, 'node_id' => 11, 'permissions' => 1]);
		$this->insert('page_content', ['page_id' => $startPage, 'node_rel_id' => $node, 'order' => 10]);
		$this->insert('widget', ['page_id' => $startPage, 'type' => 'text', 'grid_width' => 6, 'grid_height' => 2, 'settings' => ['text' => 'Mine']]);

		$this->migrate();

		$this->assertSame([['text', 0, 0, 6, 2, ['text' => 'Mine'], null]], $this->widgets($startPage));
	}

	private function migrate(): void {
		$this->migration->postSchemaChange(new NullOutput(), fn () => new SchemaWrapper($this->connection), []);
	}

	private function insertContext(string $name, string $description): int {
		return $this->insert('context', ['name' => $name, 'icon' => 'apps', 'description' => $description, 'owner_id' => 'alice', 'owner_type' => 0]);
	}

	/**
	 * @param array<string, int|string|array<string, string>> $values
	 */
	private function insert(string $table, array $values): int {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->insert('tables_contexts_' . $table)
			->values(array_map(static fn (int|string|array $value) => $qb->createNamedParameter($value, match (true) {
				is_int($value) => IQueryBuilder::PARAM_INT,
				is_array($value) => IQueryBuilder::PARAM_JSON,
				default => IQueryBuilder::PARAM_STR,
			}), $values))
			->executeStatement();
		return $qb->getLastInsertId();
	}

	/**
	 * @return list<array{string, int, int, int, int, array<string, mixed>, ?int}> type, place, settings and source of each widget
	 */
	private function widgets(int $pageId): array {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->select('w.type', 'w.grid_x', 'w.grid_y', 'w.grid_width', 'w.grid_height', 'w.settings', 'w.node_rel_id')
			->from('tables_contexts_widget', 'w')
			->where($qb->expr()->eq('w.page_id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)))
			->orderBy('w.grid_y')
			->addOrderBy('w.id');
		$result = $qb->executeQuery();
		$widgets = [];
		while ($row = $result->fetchAssociative()) {
			// MySQL stores JSON objects with their keys sorted
			$settings = json_decode((string)$row['settings'], true);
			ksort($settings);
			$widgets[] = [
				$row['type'], (int)$row['grid_x'], (int)$row['grid_y'], (int)$row['grid_width'], (int)$row['grid_height'],
				$settings, $row['node_rel_id'] === null ? null : (int)$row['node_rel_id'],
			];
		}
		$result->closeCursor();
		return $widgets;
	}
}
