<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Activity;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\RowVisibilityService;
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

	private RowVisibilityService&MockObject $rowVisibility;
	private ActivityManager $activityManager;
	private string $currentType = '';
	private string $currentUser = '';
	/** @var list<string> */
	private array $published = [];
	private array $subjectParams = [];
	private array $publishedParams = [];

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
		$view->setColumns('[3]');

		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->method('find')->willReturn($table);

		$viewMapper = $this->createMock(ViewMapper::class);
		$viewMapper->method('findAll')->willReturn([$view]);

		$shareService = $this->createMock(ShareService::class);
		$shareService->method('findSharedWithUserIds')->willReturnCallback(
			fn (int $id, string $type): array => $type === 'view' ? ['visible', 'hidden'] : [],
		);

		$event = $this->createMock(IEvent::class);
		$event->method('setSubject')->willReturnCallback(function (string $subject, array $params) use ($event): IEvent {
			$this->subjectParams = $params;
			return $event;
		});
		foreach (['setApp', 'setAuthor', 'setObject', 'setTimestamp'] as $setter) {
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
		$event->method('getSubjectParameters')->willReturnCallback(fn (): array => $this->subjectParams);

		$columnMapper = $this->createMock(ColumnMapper::class);
		$columnMapper->method('find')->willReturnCallback(function (int $id): Column {
			$column = new Column();
			$column->setId($id);
			$column->setTitle($id === 3 ? 'Status' : 'Hidden');
			$column->setType('text');
			return $column;
		});

		$manager = $this->createMock(IManager::class);
		$manager->method('generateEvent')->willReturn($event);
		$manager->method('publish')->willReturnCallback(function (): void {
			$this->published[] = $this->currentType . ':' . $this->currentUser;
			$this->publishedParams[$this->currentType] = $this->subjectParams;
		});

		$this->rowVisibility = $this->createMock(RowVisibilityService::class);

		$this->activityManager = new ActivityManager(
			$manager,
			$this->createMock(IFactory::class),
			$tableMapper,
			$viewMapper,
			$columnMapper,
			$this->rowVisibility,
			$shareService,
			new CappedMemoryCache(),
			'author',
		);
	}

	public function testSnapshotResolvesEveryViewOfTheTable(): void {
		$this->rowVisibility->expects($this->once())
			->method('usersSeeingRow')
			->with(self::ROW_ID, $this->isInstanceOf(View::class), ['author', 'visible', 'hidden']);

		$this->activityManager->snapshotRowVisibility($this->row());
	}

	public function testRowActivityReachesOnlyViewRecipientsSeeingTheRow(): void {
		$this->rowVisibility->expects($this->once())
			->method('usersSeeingRow')
			->with(self::ROW_ID, $this->isInstanceOf(View::class), ['author', 'visible', 'hidden'])
			->willReturn(['visible']);

		$this->activityManager->triggerEvent(
			objectType: ActivityManager::TABLES_OBJECT_ROW,
			object: $this->row(),
			subject: ActivityManager::SUBJECT_ROW_DELETE,
		);

		$this->assertSame([
			ActivityManager::EVENT_TYPE_TABLE_ROW . ':author',
			ActivityManager::EVENT_TYPE_VIEW_ROW . ':visible',
		], $this->published);
	}

	public function testRowActivityStoresNoCellValues(): void {
		$this->activityManager->triggerEvent(
			objectType: ActivityManager::TABLES_OBJECT_ROW,
			object: $this->row(),
			subject: ActivityManager::SUBJECT_ROW_UPDATE,
			additionalParams: [
				'before' => [['columnId' => 3, 'value' => 'secret before']],
				'after' => [['columnId' => 3, 'value' => 'secret after']],
			],
		);

		$this->assertSame(['id' => self::ROW_ID], $this->subjectParams['row']);
		$this->assertSame([['id' => 3, 'name' => 'Status']], $this->subjectParams['changeCols']);
	}

	public function testStoredTableAndViewParamsHoldOnlyIdAndTitle(): void {
		$this->rowVisibility->method('usersSeeingRow')->willReturn(['visible']);

		$this->activityManager->triggerEvent(
			objectType: ActivityManager::TABLES_OBJECT_ROW,
			object: $this->row(),
			subject: ActivityManager::SUBJECT_ROW_UPDATE,
			additionalParams: [
				'before' => [['columnId' => 3, 'value' => 'a'], ['columnId' => 9, 'value' => 'x']],
				'after' => [['columnId' => 3, 'value' => 'b'], ['columnId' => 9, 'value' => 'y']],
			],
		);

		$tableEvent = $this->publishedParams[ActivityManager::EVENT_TYPE_TABLE_ROW];
		$viewEvent = $this->publishedParams[ActivityManager::EVENT_TYPE_VIEW_ROW];
		$this->assertSame(['id' => self::TABLE_ID, 'title' => 'Table'], $tableEvent['table']);
		$this->assertSame(['id' => self::VIEW_ID, 'title' => 'View'], $viewEvent['view']);
		$this->assertSame([3, 9], array_column($tableEvent['changeCols'], 'id'));
		$this->assertSame([3], array_column($viewEvent['changeCols'], 'id'));
	}

	private function row(): Row2 {
		$row = new Row2();
		$row->setId(self::ROW_ID);
		$row->setTableId(self::TABLE_ID);
		$row->setData([['columnId' => 3, 'value' => 'secret']]);
		return $row;
	}
}
