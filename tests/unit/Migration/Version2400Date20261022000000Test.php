<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Migration;

use OC\DB\SchemaWrapper;
use OC\Migration\NullOutput;
use OCA\Tables\Migration\Version2400Date20261022000000;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * The upgrade parts run against the database.
 */
class Version2400Date20261022000000Test extends DatabaseTestCase {
	private const CONTEXT = 990401;
	private const OTHER_CONTEXT = 990402;

	private Version2400Date20261022000000 $migration;

	protected function setUp(): void {
		parent::setUp();
		$this->migration = new Version2400Date20261022000000($this->connectionAdapter);
	}

	protected function tearDown(): void {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->delete('tables_contexts_page')
			->where($qb->expr()->in('context_id', $qb->createNamedParameter([self::CONTEXT, self::OTHER_CONTEXT], IQueryBuilder::PARAM_INT_ARRAY)))
			->executeStatement();
		parent::tearDown();
	}

	public function testTheUpgradeNamesExistingPagesAndASecondRunChangesNothing(): void {
		$startPage = $this->insertPage(self::CONTEXT, 'startpage', null, null);
		$secondStartPage = $this->insertPage(self::CONTEXT, 'startpage', null, null);
		$sales = $this->insertPage(self::CONTEXT, 'grid', 'Sales', null);
		$untitled = $this->insertPage(self::CONTEXT, 'grid', null, null);
		$otherStartPage = $this->insertPage(self::OTHER_CONTEXT, 'startpage', null, null);

		$this->migration->postSchemaChange(new NullOutput(), fn () => $this->schema(), []);

		$expected = [
			$startPage => 'dashboard',
			$secondStartPage => 'page_' . $secondStartPage,
			$sales => 'page_' . $sales,
			$untitled => 'page_' . $untitled,
			$otherStartPage => 'dashboard',
		];
		$this->assertSame($expected, $this->names());

		$this->migration->postSchemaChange(new NullOutput(), fn () => $this->schema(), []);
		$this->assertSame($expected, $this->names());
	}

	public function testASecondSchemaRunChangesNothing(): void {
		$schema = $this->schema();
		$before = $this->describePageTable($schema);

		$this->migration->changeSchema(new NullOutput(), fn () => $schema, []);

		$this->assertSame($before, $this->describePageTable($schema));
		$this->assertContains('technical_name', $before['columns']);
		$this->assertContains('tables_ctx_page_name_uq', $before['indexes']);
		// The unique index starts with context_id and serves lookups by it.
		$this->assertNotContains('tables_ctx_page_ctx_idx', $before['indexes']);
	}

	public function testTheUniqueIndexRejectsASecondPageWithTheSameName(): void {
		$this->insertPage(self::CONTEXT, 'grid', 'Sales', 'sales');
		$this->insertPage(self::OTHER_CONTEXT, 'grid', 'Sales', 'sales');

		try {
			$this->insertPage(self::CONTEXT, 'grid', 'Sales', 'sales');
			$this->fail('The unique index accepted a second page named sales');
		} catch (Exception $e) {
			$this->assertSame(Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION, $e->getReason());
		}
	}

	public function testTheDatabaseAllowsPagesWithoutANameThatTheCodeNeverWrites(): void {
		$this->assertFalse($this->schema()->getTable('tables_contexts_page')->getColumn('technical_name')->getNotnull());

		$first = $this->insertPage(self::CONTEXT, 'grid', null, null);
		$second = $this->insertPage(self::CONTEXT, 'grid', null, null);

		$this->assertSame([$first => null, $second => null], $this->names());
	}
	public function testBackfillNamesTheStartPageDashboardAndOtherPagesByTheirId(): void {
		$names = Version2400Date20261022000000::backfillNames([
			['id' => 1, 'context_id' => 10, 'page_type' => 'startpage', 'technical_name' => null],
			['id' => 2, 'context_id' => 10, 'page_type' => 'grid', 'technical_name' => null],
			['id' => 3, 'context_id' => 10, 'page_type' => 'startpage', 'technical_name' => ''],
			['id' => 6, 'context_id' => 11, 'page_type' => 'startpage', 'technical_name' => null],
		]);

		$this->assertSame([1 => 'dashboard', 2 => 'page_2', 3 => 'page_3', 6 => 'dashboard'], $names);
	}

	public function testBackfillLeavesNamedPagesAloneAndGivesTheirNamesToNoOne(): void {
		$this->assertSame([], Version2400Date20261022000000::backfillNames([
			['id' => 1, 'context_id' => 10, 'page_type' => 'startpage', 'technical_name' => 'dashboard'],
			['id' => 2, 'context_id' => 10, 'page_type' => 'grid', 'technical_name' => 'sales'],
		]));
		$this->assertSame([], Version2400Date20261022000000::backfillNames([]));
		$this->assertSame([1 => 'page_1'], Version2400Date20261022000000::backfillNames([
			['id' => 1, 'context_id' => 10, 'page_type' => 'startpage', 'technical_name' => null],
			['id' => 2, 'context_id' => 10, 'page_type' => 'grid', 'technical_name' => 'dashboard'],
		]));
	}

	public function testTheLowerPageIdKeepsDashboardWhateverTheRowOrder(): void {
		$names = Version2400Date20261022000000::backfillNames([
			['id' => 9, 'context_id' => 10, 'page_type' => 'startpage', 'technical_name' => null],
			['id' => 3, 'context_id' => 10, 'page_type' => 'startpage', 'technical_name' => null],
		]);

		$this->assertSame([3 => 'dashboard', 9 => 'page_9'], $names);
	}

	private function insertPage(int $contextId, string $pageType, ?string $title, ?string $technicalName): int {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->insert('tables_contexts_page')
			->values([
				'context_id' => $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT),
				'page_type' => $qb->createNamedParameter($pageType),
				'title' => $qb->createNamedParameter($title),
				'technical_name' => $qb->createNamedParameter($technicalName),
			])
			->executeStatement();
		return $qb->getLastInsertId();
	}

	/**
	 * @return array<int, ?string> technical name by page id
	 */
	private function names(): array {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->select('id', 'technical_name')
			->from('tables_contexts_page')
			->where($qb->expr()->in('context_id', $qb->createNamedParameter([self::CONTEXT, self::OTHER_CONTEXT], IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('id');
		$result = $qb->executeQuery();
		$names = [];
		while ($row = $result->fetchAssociative()) {
			$names[(int)$row['id']] = $row['technical_name'];
		}
		$result->closeCursor();
		return $names;
	}

	private function schema(): SchemaWrapper {
		return new SchemaWrapper($this->connection);
	}

	/**
	 * @return array{columns: list<string>, indexes: list<string>}
	 */
	private function describePageTable(SchemaWrapper $schema): array {
		$table = $schema->getTable('tables_contexts_page');
		$columns = array_map(static fn ($column) => $column->getName(), $table->getColumns());
		$indexes = array_map(static fn ($index) => $index->getName(), $table->getIndexes());
		sort($columns);
		sort($indexes);
		return ['columns' => $columns, 'indexes' => $indexes];
	}
}
