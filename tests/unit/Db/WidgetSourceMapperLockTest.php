<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\WidgetSourceMapper;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class WidgetSourceMapperLockTest extends TestCase {
	public static function platforms(): array {
		return [
			'SQLite, which has no row locks' => [IDBConnection::PLATFORM_SQLITE, 0],
			'PostgreSQL' => [IDBConnection::PLATFORM_POSTGRES, 1],
			'MySQL' => [IDBConnection::PLATFORM_MYSQL, 1],
		];
	}

	/**
	 * @dataProvider platforms
	 */
	public function testRelationsAreLockedWhereTheDatabaseHasRowLocks(string $platform, int $locks): void {
		$qb = $this->createMock(IQueryBuilder::class);
		foreach (['select', 'from', 'where', 'orderBy', 'setParameter'] as $method) {
			$qb->method($method)->willReturnSelf();
		}
		$qb->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$qb->method('executeQuery')->willReturn($this->createMock(IResult::class));
		$qb->expects($this->exactly($locks))->method('forUpdate')->willReturnSelf();
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willReturn($qb);
		$db->method('getDatabaseProvider')->willReturn($platform);

		(new WidgetSourceMapper($db))->lockNodeRelations([7]);
	}
}
