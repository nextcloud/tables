<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Notification;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\ConfigService;
use OCA\Tables\Service\ShareService;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;

final class NotificationHelperPayloadTest extends TestCase {
	private NotificationHelper $helper;
	private array $subjectParams = [];

	protected function setUp(): void {
		parent::setUp();

		$table = new Table();
		$table->setId(1);
		$table->setTitle('Table');
		$table->setOwnership('author');

		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->method('find')->willReturn($table);

		$viewMapper = $this->createMock(ViewMapper::class);
		$viewMapper->method('findAll')->willReturn([]);

		$columnMapper = $this->createMock(ColumnMapper::class);
		$columnMapper->method('find')->willReturnCallback(function (int $id): Column {
			$column = new Column();
			$column->setId($id);
			$column->setTitle('Status');
			$column->setType('text');
			return $column;
		});

		$activityManager = $this->createMock(ActivityManager::class);
		$activityManager->method('findRecipientsByElement')->willReturn(['author', 'follower']);

		$configService = $this->createMock(ConfigService::class);
		$configService->method('isNotifyEnabledForScope')->willReturn(true);

		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setDateTime', 'setObject', 'setUser'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}
		$notification->method('setSubject')->willReturnCallback(function (string $subject, array $params) use ($notification): INotification {
			$this->subjectParams = $params;
			return $notification;
		});

		$notificationManager = $this->createMock(IManager::class);
		$notificationManager->method('createNotification')->willReturn($notification);

		$this->helper = new NotificationHelper(
			$notificationManager,
			$configService,
			$this->createMock(ShareService::class),
			$tableMapper,
			$viewMapper,
			$this->createMock(Row2Mapper::class),
			$columnMapper,
			$activityManager,
			'author',
		);
	}

	public function testRowUpdateStoresNoCellValues(): void {
		$row = new Row2();
		$row->setId(42);
		$row->setTableId(1);
		$row->setData([['columnId' => 3, 'value' => 'secret']]);

		$this->helper->sendNotification(ActivityManager::TABLES_OBJECT_ROW, $row, ActivityManager::SUBJECT_ROW_UPDATE, [
			'before' => [['columnId' => 3, 'value' => 'secret before']],
			'after' => [['columnId' => 3, 'value' => 'secret after']],
		], 'author');

		$this->assertSame([['id' => 3, 'name' => 'Status', 'type' => 'text']], $this->subjectParams['changeCols']);
		$this->assertSame(['id' => 42], $this->subjectParams['row']);
	}
}
