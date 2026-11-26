<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Table;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableTemplateService;
use OCA\Tables\Service\ViewService;
use OCP\Defaults;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TableTemplateServiceTest extends TestCase {
	private ColumnService&MockObject $columnService;
	private RowService&MockObject $rowService;

	protected function setUp(): void {
		parent::setUp();
		$this->columnService = $this->createMock(ColumnService::class);
		$this->rowService = $this->createMock(RowService::class);
	}

	/**
	 * occ has no session user, so the template content has to be created as the
	 * owner of the freshly created table. Without that the column service was
	 * never called and the template builders ran into a null reference.
	 */
	public function testCreatesTemplateContentAsTheTableOwnerWithoutASessionUser(): void {
		$columnOwners = [];
		$this->columnService->method('create')
			->willReturnCallback(function (string $userId) use (&$columnOwners): Column {
				$columnOwners[] = $userId;
				return $this->createColumnStub(count($columnOwners));
			});

		$rowOwner = null;
		$this->rowService->expects($this->once())
			->method('create')
			->willReturnCallback(function (?int $tableId, ?int $viewId, $data, ?string $userId) use (&$rowOwner): Row2 {
				$rowOwner = $userId;
				return $this->createMock(Row2::class);
			});

		$this->buildService(null)->makeTemplate($this->createTable('alice'), 'members');

		$this->assertSame(['alice', 'alice', 'alice', 'alice', 'alice'], $columnOwners);
		$this->assertSame('alice', $rowOwner);
	}

	public function testPrefersTheSessionUserWhenThereIsOne(): void {
		$columnOwners = [];
		$this->columnService->method('create')
			->willReturnCallback(function (string $userId) use (&$columnOwners): Column {
				$columnOwners[] = $userId;
				return $this->createColumnStub(count($columnOwners));
			});
		$this->rowService->method('create')->willReturn($this->createMock(Row2::class));

		$this->buildService('bob')->makeTemplate($this->createTable('alice'), 'members');

		$this->assertSame(['bob', 'bob', 'bob', 'bob', 'bob'], $columnOwners);
	}

	private function buildService(?string $sessionUserId): TableTemplateService {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		return new TableTemplateService(
			$this->createMock(LoggerInterface::class),
			$l10n,
			$this->columnService,
			$sessionUserId,
			$this->rowService,
			$this->createMock(ViewService::class),
			$this->createMock(Defaults::class),
		);
	}

	private function createTable(string $ownership): Table {
		$table = new Table();
		$table->setId(1);
		$table->setOwnership($ownership);
		return $table;
	}

	private function createColumnStub(int $id): Column {
		$column = new Column();
		$column->setId($id);
		return $column;
	}
}
