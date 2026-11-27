<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Migration;

use OC\DB\SchemaWrapper;
use OC\Migration\NullOutput;
use OCA\Tables\Migration\Version2500Date20261109030000;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * The upgrade part runs against the database.
 */
class Version2500Date20261109030000Test extends DatabaseTestCase {
	private const CONTEXT = 990501;
	private const OTHER_CONTEXT = 990502;
	private const CONTEXT_WITH_MENU = 990503;

	protected function tearDown(): void {
		$contextIds = [self::CONTEXT, self::OTHER_CONTEXT, self::CONTEXT_WITH_MENU];
		foreach (['tables_contexts_page', 'tables_contexts_menu_item'] as $table) {
			$qb = $this->connectionAdapter->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->in('context_id', $qb->createNamedParameter($contextIds, IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		parent::tearDown();
	}

	public function testTheUpgradeGivesEveryApplicationWithoutAMenuItsStartPageAndASecondRunChangesNothing(): void {
		$startPage = $this->insertPage(self::CONTEXT, 'startpage');
		$this->insertPage(self::CONTEXT, 'startpage');
		$this->insertPage(self::CONTEXT, 'grid');
		$otherStartPage = $this->insertPage(self::OTHER_CONTEXT, 'startpage');
		$this->insertPage(self::CONTEXT_WITH_MENU, 'startpage');
		$this->insertMenuItem(self::CONTEXT_WITH_MENU, 'Links');
		$migration = new Version2500Date20261109030000($this->connectionAdapter);

		$migration->postSchemaChange(new NullOutput(), fn () => new SchemaWrapper($this->connection), []);

		$expected = [
			[self::CONTEXT, 0, $startPage, null],
			[self::OTHER_CONTEXT, 0, $otherStartPage, null],
			[self::CONTEXT_WITH_MENU, 0, null, 'Links'],
		];
		$this->assertSame($expected, $this->menuItems());

		$migration->postSchemaChange(new NullOutput(), fn () => new SchemaWrapper($this->connection), []);
		$this->assertSame($expected, $this->menuItems());
	}

	private function insertPage(int $contextId, string $pageType): int {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->insert('tables_contexts_page')
			->values([
				'context_id' => $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT),
				'page_type' => $qb->createNamedParameter($pageType),
			])
			->executeStatement();
		return $qb->getLastInsertId();
	}

	private function insertMenuItem(int $contextId, string $title): void {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->insert('tables_contexts_menu_item')
			->values([
				'context_id' => $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT),
				'title' => $qb->createNamedParameter($title),
			])
			->executeStatement();
	}

	/**
	 * @return list<array{int, int, ?int, ?string}> application, order, page and title of every item
	 */
	private function menuItems(): array {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->select('context_id', 'order', 'page_id', 'title')
			->from('tables_contexts_menu_item')
			->where($qb->expr()->in('context_id', $qb->createNamedParameter([self::CONTEXT, self::OTHER_CONTEXT, self::CONTEXT_WITH_MENU], IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('context_id');
		$result = $qb->executeQuery();
		$items = [];
		while ($row = $result->fetchAssociative()) {
			$items[] = [(int)$row['context_id'], (int)$row['order'], $row['page_id'] === null ? null : (int)$row['page_id'], $row['title']];
		}
		$result->closeCursor();
		return $items;
	}
}
