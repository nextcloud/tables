<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Helper\UserHelper;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class TableMapperTest extends TestCase {
	/**
	 * @dataProvider databaseProviders
	 */
	public function testFindForUpdateRefreshesCacheAndUsesSupportedLock(string $provider, bool $supportsRowLock): void {
		$query = $this->createMock(IQueryBuilder::class);
		$expression = $this->createMock(IExpressionBuilder::class);
		$query->method('select')->willReturnSelf();
		$query->method('from')->willReturnSelf();
		$query->method('where')->willReturnSelf();
		$query->method('expr')->willReturn($expression);
		$query->method('createNamedParameter')->willReturn(':id');
		$expression->method('eq')->willReturn('id = :id');
		$query->expects($supportsRowLock ? self::once() : self::never())->method('forUpdate')->willReturnSelf();

		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getDatabaseProvider')->willReturn($provider);
		$connection->method('getQueryBuilder')->willReturn($query);
		$mapper = $this->getMockBuilder(TableMapper::class)
			->setConstructorArgs([$connection, $this->createMock(UserHelper::class)])
			->onlyMethods(['findEntity'])
			->getMock();
		$stale = new Table();
		$current = new Table();
		$mapper->expects(self::exactly(2))->method('findEntity')->willReturnOnConsecutiveCalls($stale, $current);

		self::assertSame($stale, $mapper->find(5));
		self::assertSame($current, $mapper->findForUpdate(5));
		self::assertSame($current, $mapper->find(5));
	}

	public static function databaseProviders(): array {
		return [
			'sqlite' => [IDBConnection::PLATFORM_SQLITE, false],
			'postgresql' => [IDBConnection::PLATFORM_POSTGRES, true],
			'mysql' => [IDBConnection::PLATFORM_MYSQL, true],
		];
	}

}
