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
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ShareService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Cache\CappedMemoryCache;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ActivityManagerTest extends TestCase {
	private const TABLE_ID = 1;
	private const VIEW_ID = 7;
	private const ROW_ID = 42;

	private Row2Mapper&MockObject $row2Mapper;
	private ActivityManager $activityManager;
	private string $currentType = '';
	private string $currentUser = '';
	/** @var list<string> */
	private array $published = [];

	protected function setUp(): void {
		parent::setUp();

		$table = new Table();
		$table->setId(self::TABLE_ID);
		$table->setTitle('Table');
		$table->setOwnership('author');

		$view = new View();
		$view->setId(self::VIEW_ID);
		$view->setTitle('View');
		$view->setTableId(self::TABLE_ID);
		$view->setOwnership('author');
		$view->setColumns('[]');

		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->method('find')->willReturn($table);

		$viewMapper = $this->createMock(ViewMapper::class);
		$viewMapper->method('findAll')->willReturn([$view]);

		$shareService = $this->createMock(ShareService::class);
		$shareService->method('findSharedWithUserIds')->willReturnCallback(
			fn (int $id, string $type): array => $type === 'view' ? ['visible', 'hidden'] : [],
		);

		$event = $this->createMock(IEvent::class);
		foreach (['setApp', 'setAuthor', 'setObject', 'setSubject', 'setTimestamp'] as $setter) {
			$event->method($setter)->willReturnSelf();
		}
		$event->method('setType')->willReturnCallback(function (string $type) use ($event): IEvent {
			$this->currentType = $type;
			return $event;
		});
		$event->method('setAffectedUser')->willReturnCallback(function (string $userId) use ($event): IEvent {
			$this->currentUser = $userId;
			return $event;
		});
		$event->method('getSubject')->willReturn(ActivityManager::SUBJECT_ROW_DELETE);
		$event->method('getSubjectParameters')->willReturn([]);

		$manager = $this->createMock(IManager::class);
		$manager->method('generateEvent')->willReturn($event);
		$manager->method('publish')->willReturnCallback(function (): void {
			$this->published[] = $this->currentType . ':' . $this->currentUser;
		});

		$this->row2Mapper = $this->createMock(Row2Mapper::class);

		$this->activityManager = new ActivityManager(
			$manager,
			$this->createMock(IFactory::class),
			$tableMapper,
			$viewMapper,
			$this->createMock(ColumnMapper::class),
			$this->row2Mapper,
			$shareService,
			new CappedMemoryCache(),
			'author',
		);
	}

	public function testFindViewRecipientsSeeingRowIsKeyedByView(): void {
		$this->row2Mapper->expects($this->once())
			->method('filterUsersSeeingRow')
			->with(self::ROW_ID, $this->isInstanceOf(View::class), ['author', 'visible', 'hidden'])
			->willReturn(['author', 'visible']);

		$this->assertSame([self::VIEW_ID => ['author', 'visible']], $this->activityManager->findViewRecipientsSeeingRow($this->row()));
	}

	public function testDeleteActivityUsesPrecomputedViewRecipients(): void {
		$this->row2Mapper->expects($this->never())->method('filterUsersSeeingRow');

		$this->activityManager->triggerEvent(
			objectType: ActivityManager::TABLES_OBJECT_ROW,
			object: $this->row(),
			subject: ActivityManager::SUBJECT_ROW_DELETE,
			viewRecipientsSeeingRow: [self::VIEW_ID => ['visible']],
		);

		$this->assertSame([
			ActivityManager::EVENT_TYPE_TABLE_ROW . ':author',
			ActivityManager::EVENT_TYPE_VIEW_ROW . ':visible',
		], $this->published);
	}

	private function row(): Row2 {
		$row = new Row2();
		$row->setId(self::ROW_ID);
		$row->setTableId(self::TABLE_ID);
		$row->setData([]);
		return $row;
	}
}
