<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Activity;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\RowVisibilityService;
use OCA\Tables\Service\ShareService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Cache\CappedMemoryCache;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

final class ActivityManagerRecipientsTest extends TestCase {
	private ActivityManager $activityManager;
	private Table $table;
	/** @var list<string> */
	private array $affectedUsers = [];

	protected function setUp(): void {
		parent::setUp();

		$this->table = new Table();
		$this->table->setId(1);
		$this->table->setTitle('Table');
		$this->table->setOwnership('owner');

		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->method('find')->willReturn($this->table);

		$shareService = $this->createMock(ShareService::class);
		$shareService->method('findSharedWithUserIds')->willReturn([123, 'alice']);

		$event = $this->createMock(IEvent::class);
		foreach (['setApp', 'setType', 'setAuthor', 'setObject', 'setSubject', 'setTimestamp'] as $setter) {
			$event->method($setter)->willReturnSelf();
		}
		$event->method('setAffectedUser')->willReturnCallback(function (string $userId) use ($event): IEvent {
			$this->affectedUsers[] = $userId;
			return $event;
		});

		$manager = $this->createMock(IManager::class);
		$manager->method('generateEvent')->willReturn($event);

		$this->activityManager = new ActivityManager(
			$manager,
			$this->createMock(IFactory::class),
			$tableMapper,
			$this->createMock(ViewMapper::class),
			$this->createMock(ColumnMapper::class),
			$this->createMock(RowVisibilityService::class),
			$shareService,
			new CappedMemoryCache(),
			'owner',
		);
	}

	public function testNumericShareReceiversAreReturnedAsStrings(): void {
		$this->assertSame(['owner', '123', 'alice'], $this->activityManager->findRecipientsByElement($this->table));
	}

	public function testRowActivityReachesNumericUserIds(): void {
		$row = new Row2();
		$row->setId(42);
		$row->setTableId(1);
		$row->setData([]);

		$this->activityManager->triggerEvent(ActivityManager::TABLES_OBJECT_ROW, $row, ActivityManager::SUBJECT_ROW_CREATE);

		$this->assertSame(['owner', '123', 'alice'], $this->affectedUsers);
	}
}
