<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\FormattingRuleColMapper;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class FormattingRuleColMapperTest extends TestCase {

	private function makeQb(): IQueryBuilder {
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturnArgument(0);

		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('select')->willReturnSelf();
		$qb->method('insert')->willReturnSelf();
		$qb->method('delete')->willReturnSelf();
		$qb->method('from')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('values')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturnArgument(0);
		$qb->method('expr')->willReturn($expr);

		$result = $this->createMock(\Doctrine\DBAL\Result::class);
		$result->method('fetch')->willReturn(false);
		$qb->method('executeQuery')->willReturn($result);
		$qb->method('executeStatement')->willReturn(1);

		return $qb;
	}

	public function testSyncForRuleWithEmptyColumnIdsOnlyDeletesByRule(): void {
		$qb = $this->makeQb();
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())
			->method('getQueryBuilder')
			->willReturn($qb);

		$qb->expects($this->once())->method('delete');
		$qb->expects($this->never())->method('insert');

		$mapper = new FormattingRuleColMapper($db);
		$mapper->syncForRule('rule-1', 5, []);
	}

	public function testSyncForRuleWithColumnIdsDeletesThenInserts(): void {
		$qbs = array_map(fn() => $this->makeQb(), range(0, 2)); // delete + 2 inserts
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->exactly(3))
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls(...$qbs);

		$qbs[0]->expects($this->once())->method('delete');
		$qbs[1]->expects($this->once())->method('insert');
		$qbs[2]->expects($this->once())->method('insert');

		$mapper = new FormattingRuleColMapper($db);
		$mapper->syncForRule('rule-1', 5, [10, 20]);
	}

	public function testFindRuleIdsByColumnReturnsEmptyWhenNoRows(): void {
		$qb = $this->makeQb();
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);

		$mapper = new FormattingRuleColMapper($db);
		$result = $mapper->findRuleIdsByColumn(99);

		$this->assertSame([], $result);
	}

	public function testDeleteByViewCallsDeleteWithCorrectCondition(): void {
		$qb = $this->makeQb();
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);

		$qb->expects($this->once())->method('delete')->with('tables_fmt_rule_cols');
		$qb->expects($this->once())->method('executeStatement');

		$mapper = new FormattingRuleColMapper($db);
		$mapper->deleteByView(7);
	}

	public function testDeleteByRuleCallsDeleteWithCorrectTable(): void {
		$qb = $this->makeQb();
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);

		$qb->expects($this->once())->method('delete')->with('tables_fmt_rule_cols');

		$mapper = new FormattingRuleColMapper($db);
		$mapper->deleteByRule('rule-abc');
	}
}
