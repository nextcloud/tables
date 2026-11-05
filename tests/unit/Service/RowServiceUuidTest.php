<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\RowSleeve;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RowServiceUuidTest extends TestCase {
	private RowService $service;
	private Row2Mapper $rows;

	protected function setUp(): void {
		$this->rows = $this->createMock(Row2Mapper::class);
		$this->service = new RowService(new NullLogger(), 'alice', $this->createMock(PermissionsService::class), $this->createMock(ColumnMapper::class),
			$this->createMock(ViewMapper::class), $this->createMock(TableMapper::class), $this->rows, $this->createMock(IEventDispatcher::class),
			$this->createMock(ColumnsHelper::class), $this->createMock(ActivityManager::class), $this->createMock(NotificationHelper::class));
	}

	private function row(): Row2 {
		$row = new Row2();
		$row->setId(13);
		$row->setTableId(5);
		$row->setUuid('019f90c5-8576-7f0b-9b14-9bf49b2826cf');
		$row->setData([['columnId' => 1, 'value' => 'visible'], ['columnId' => 2, 'value' => 'hidden']]);
		return $row;
	}

	/** @dataProvider archiveIdentities */
	public function testArchiveIdentityGoesThroughSleeveInsertionBoundary(mixed $uuid, ?string $expected, bool $includeUuid = true): void {
		$table = new Table();
		$table->setId(5);
		$table->setOwnership('alice');
		$this->rows->expects($this->once())->method('importSleeve')->willReturnCallback(function (RowSleeve $sleeve) use ($expected): RowSleeve {
			$this->assertSame($expected, $sleeve->getUuid());
			$this->assertSame(5, $sleeve->getTableId());
			$this->assertSame('alice', $sleeve->getCreatedBy());
			$sleeve->setId(99);
			$sleeve->assignUuid();
			return $sleeve;
		});
		$archive = ['createdAt' => '2020-01-01 00:00:00', 'lastEditAt' => '2020-01-02 00:00:00'];
		if ($includeUuid) {
			$archive['uuid'] = $uuid;
		}
		$this->assertSame(99, $this->service->importRow($table, $archive));
	}

	public static function archiveIdentities(): array {
		return [[null, null, false], [null, null], ['019F90C5-8576-7F0B-9B14-9BF49B2826CF', '019f90c5-8576-7f0b-9b14-9bf49b2826cf']];
	}

	/** @dataProvider invalidArchiveIdentities */
	public function testInvalidArchiveIdentityFailsBeforeWriting(mixed $uuid): void {
		$this->rows->expects($this->never())->method('importSleeve');
		$this->expectException(\InvalidArgumentException::class);
		$table = new Table();
		$table->setId(5);
		$table->setOwnership('alice');
		$this->service->importRow($table, ['uuid' => $uuid]);
	}

	public static function invalidArchiveIdentities(): array {
		return [[3], [[]], [''], ['019f90c5-8576-4f0b-9b14-9bf49b2826cf']];
	}

	public function testPublicFormatterKeepsUuidAndRemovesPrivateMetadata(): void {
		$row = $this->row();
		$result = $this->service->formatRowsForPublicShare([$row])[0];
		$this->assertSame($row->getUuid(), $result['uuid']);
		$this->assertArrayNotHasKey('tableId', $result);
		$this->assertArrayNotHasKey('createdBy', $result);
		$this->assertArrayNotHasKey('lastEditBy', $result);
	}
}
