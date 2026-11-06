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
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\ConfigService;
use OCA\Tables\Service\RowVisibilityService;
use OCA\Tables\Service\ShareService;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class NotificationHelperTest extends TestCase {
	private const TABLE_ID = 1;
	private const ROW_ID = 42;

	private RowVisibilityService&MockObject $rowVisibility;
	private NotificationHelper $helper;
	private View $view;
	/** @var list<string> */
	private array $notifiedUsers = [];
	private array $subjectParams = [];

	protected function setUp(): void {
		parent::setUp();

		$table = new Table();
		$table->setId(self::TABLE_ID);
		$table->setTitle('Table');
		$table->setOwnership('author');

		$this->view = new View();
		$this->view->setId(7);
		$this->view->setTitle('View');
		$this->view->setTableId(self::TABLE_ID);
		$this->view->setColumns('[3]');

		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->method('find')->willReturn($table);

		$viewMapper = $this->createMock(ViewMapper::class);
		$viewMapper->method('findAll')->willReturn([$this->view]);

		$activityManager = $this->createMock(ActivityManager::class);
		$activityManager->method('findRecipientsByElement')->willReturnCallback(
			fn (Table|View $element): array => $element instanceof View ? ['author', 'visible', 'hidden', 'not-subscribed'] : ['author'],
		);

		$configService = $this->createMock(ConfigService::class);
		$configService->method('isNotifyEnabledForScope')->willReturnCallback(
			fn (string $userId): bool => $userId !== 'not-subscribed',
		);

		$notification = $this->createMock(INotification::class);
		$notification->method('setSubject')->willReturnCallback(function (string $subject, array $params) use ($notification): INotification {
			$this->subjectParams = $params;
			return $notification;
		});
		foreach (['setApp', 'setDateTime', 'setObject'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}
		$notification->method('setUser')->willReturnCallback(function (string $userId) use ($notification): INotification {
			$this->notifiedUsers[] = $userId;
			return $notification;
		});

		$column = new Column();
		$column->setId(3);
		$column->setTitle('Status');
		$column->setType('text');
		$columnMapper = $this->createMock(ColumnMapper::class);
		$columnMapper->method('find')->willReturn($column);

		$notificationManager = $this->createMock(IManager::class);
		$notificationManager->method('createNotification')->willReturn($notification);

		$this->rowVisibility = $this->createMock(RowVisibilityService::class);

		$this->helper = new NotificationHelper(
			$notificationManager,
			$configService,
			$this->createMock(ShareService::class),
			$tableMapper,
			$viewMapper,
			$this->rowVisibility,
			$columnMapper,
			$activityManager,
			'author',
		);
	}

	public function testViewNotificationOnlyReachesSubscribersSeeingTheRow(): void {
		$this->rowVisibility->expects($this->once())
			->method('usersSeeingRow')
			->with(self::ROW_ID, $this->view, ['visible', 'hidden'])
			->willReturn(['visible']);

		$this->sendRowCreated();

		$this->assertSame(['visible'], $this->notifiedUsers);
	}

	public function testNoViewNotificationWhenRowIsNotInView(): void {
		$this->rowVisibility->method('usersSeeingRow')->willReturn([]);

		$this->sendRowCreated();

		$this->assertSame([], $this->notifiedUsers);
	}

	public function testRowNotificationStoresNoCellValues(): void {
		$this->rowVisibility->method('usersSeeingRow')->willReturn(['visible']);

		$this->helper->sendNotification(ActivityManager::TABLES_OBJECT_ROW, $this->row(), ActivityManager::SUBJECT_ROW_UPDATE, [
			'before' => [['columnId' => 3, 'value' => 'secret before']],
			'after' => [['columnId' => 3, 'value' => 'secret after']],
		], 'author');

		$this->assertSame([['id' => 3, 'name' => 'Status', 'type' => 'text']], $this->subjectParams['changeCols']);
		$this->assertSame(['id' => self::ROW_ID], $this->subjectParams['row']);
	}

	private function sendRowCreated(): void {
		$this->helper->sendNotification(ActivityManager::TABLES_OBJECT_ROW, $this->row(), ActivityManager::SUBJECT_ROW_CREATE, [], 'author');
	}

	private function row(): Row2 {
		$row = new Row2();
		$row->setId(self::ROW_ID);
		$row->setTableId(self::TABLE_ID);
		$row->setData([]);
		return $row;
	}
}
