<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Notification;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\View;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Notification\Notifier;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowVisibilityService;
use OCA\Tables\Service\ViewService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class NotifierTest extends TestCase {
	private const TABLE_ID = 1;
	private const VIEW_ID = 7;
	private const ROW_ID = 42;
	private const TITLE_COLUMN_ID = 3;
	private const RECIPIENT = 'recipient';

	private PermissionsService&MockObject $permissionsService;
	private Row2Mapper&MockObject $row2Mapper;
	private ViewService&MockObject $viewService;
	private ColumnMapper&MockObject $columnMapper;
	private RowVisibilityService&MockObject $rowVisibilityService;
	private Notifier $notifier;
	private IFactory&MockObject $l10nFactory;
	private IURLGenerator&MockObject $url;

	private string $richSubject = '';
	private array $richParams = [];
	private string $parsedSubject = '';

	protected function setUp(): void {
		parent::setUp();

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(
			static fn (string $text, $parameters = []): string => vsprintf($text, is_array($parameters) ? $parameters : [$parameters]),
		);
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l);

		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturn('https://cloud.example/apps/tables/');

		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->row2Mapper = $this->createMock(Row2Mapper::class);
		$this->viewService = $this->createMock(ViewService::class);
		$this->columnMapper = $this->createMock(ColumnMapper::class);
		$this->rowVisibilityService = $this->createMock(RowVisibilityService::class);
		// by default the recipient still sees the row
		$this->rowVisibilityService->method('usersSeeingRow')->willReturnArgument(2);

		$this->l10nFactory = $l10nFactory;
		$this->url = $url;
		$this->rebuildNotifier();
	}

	private function rebuildNotifier(): void {
		$this->notifier = new Notifier(
			$this->l10nFactory,
			$this->url,
			$this->createMock(IUserManager::class),
			$this->permissionsService,
			$this->row2Mapper,
			$this->viewService,
			$this->columnMapper,
			$this->rowVisibilityService,
		);
	}

	public function testTableContextUsesRowIdAsName(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(true);
		$this->row2Mapper->expects($this->never())->method('find');

		$this->prepare(ActivityManager::SUBJECT_ROW_UPDATE, $this->tableParams());

		$this->assertSame('#42', $this->richParams['row']['name']);
		$this->assertSame('{user} has updated row {row} in table {table}', $this->richSubject);
	}

	public function testTableContextChecksTablePermission(): void {
		$this->permissionsService->expects($this->once())
			->method('canAccessNodeById')
			->with(Application::NODE_TYPE_TABLE, self::TABLE_ID)
			->willReturn(false);

		$this->expectException(UnknownNotificationException::class);
		$this->prepare(ActivityManager::SUBJECT_ROW_UPDATE, $this->tableParams());
	}

	public function testViewContextOnlyChecksViewPermission(): void {
		// a recipient with only a view share cannot read the table
		$this->permissionsService->expects($this->once())
			->method('canAccessNodeById')
			->with(Application::NODE_TYPE_VIEW, self::VIEW_ID)
			->willReturn(true);

		$this->prepare(ActivityManager::SUBJECT_ROW_ASSIGN, $this->viewParams(null));

		$this->assertSame('#42', $this->richParams['row']['name']);
		$this->assertStringContainsString('/view/7/row/42', $this->richParams['row']['link']);
	}

	public function testViewContextDeniedWithoutViewPermission(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(false);

		$this->expectException(UnknownNotificationException::class);
		$this->prepare(ActivityManager::SUBJECT_ROW_ASSIGN, $this->viewParams(self::TITLE_COLUMN_ID));
	}

	public function testViewContextUsesTitleSourceValue(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(true);
		$this->mockView([1, self::TITLE_COLUMN_ID]);
		$this->mockTitleCell('Buy milk');

		$this->prepare(ActivityManager::SUBJECT_ROW_ASSIGN, $this->viewParams(self::TITLE_COLUMN_ID));

		$this->assertSame('Buy milk', $this->richParams['row']['name']);
		$this->assertSame('You have been assigned in row Buy milk of view View by .', $this->parsedSubject);
	}

	public function testRichTextTitleIsStrippedAndTrimmed(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(true);
		$this->mockView([self::TITLE_COLUMN_ID]);
		$this->mockTitleCell("  <p><strong>Buy</strong> milk</p>\n");

		$this->prepare(ActivityManager::SUBJECT_ROW_UPDATE, $this->viewParams(self::TITLE_COLUMN_ID));

		$this->assertSame('Buy milk', $this->richParams['row']['name']);
		$this->assertSame('{user} has updated row {row} in view {view}', $this->richSubject);
	}

	public function testWhitespaceOnlyTitleFallsBackToRowId(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(true);
		$this->mockView([self::TITLE_COLUMN_ID]);
		$this->mockTitleCell('<p> </p>');

		$this->prepare(ActivityManager::SUBJECT_ROW_UPDATE, $this->viewParams(self::TITLE_COLUMN_ID));

		$this->assertSame('#42', $this->richParams['row']['name']);
	}

	public function testTitleSourceNotInViewFallsBackToRowId(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(true);
		$this->mockView([1, 2]);
		$this->columnMapper->expects($this->never())->method('find');
		$this->row2Mapper->expects($this->never())->method('find');

		$this->prepare(ActivityManager::SUBJECT_ROW_UPDATE, $this->viewParams(self::TITLE_COLUMN_ID));

		$this->assertSame('#42', $this->richParams['row']['name']);
	}

	public function testMissingRowFallsBackToRowId(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(true);
		$this->mockView([self::TITLE_COLUMN_ID]);
		$this->columnMapper->method('find')->willReturn($this->titleColumn());
		$this->row2Mapper->method('find')->willThrowException(new NotFoundError('gone'));

		$this->prepare(ActivityManager::SUBJECT_ROW_UPDATE, $this->viewParams(self::TITLE_COLUMN_ID));

		$this->assertSame('#42', $this->richParams['row']['name']);
	}

	public function testInaccessibleViewFallsBackToRowId(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(true);
		$this->viewService->method('find')->willThrowException(new PermissionError('nope'));

		$this->prepare(ActivityManager::SUBJECT_ROW_UPDATE, $this->viewParams(self::TITLE_COLUMN_ID));

		$this->assertSame('#42', $this->richParams['row']['name']);
	}

	public function testDeleteSubjectDoesNotReferenceRow(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(true);

		$this->prepare(ActivityManager::SUBJECT_ROW_DELETE, $this->tableParams());
		$this->assertSame('{user} has deleted a row in table {table}', $this->richSubject);

		$this->prepare(ActivityManager::SUBJECT_ROW_DELETE, $this->viewParams(null));
		$this->assertSame('{user} has deleted a row in view {view}', $this->richSubject);
	}

	public function testVisibilityIsCheckedForRecipient(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(true);
		$this->rowVisibilityService = $this->createMock(RowVisibilityService::class);
		$this->rowVisibilityService->expects($this->once())
			->method('usersSeeingRow')
			->with(self::ROW_ID, $this->isInstanceOf(View::class), [self::RECIPIENT])
			->willReturn([self::RECIPIENT]);
		$this->rebuildNotifier();
		$this->mockView([self::TITLE_COLUMN_ID]);
		$this->mockTitleCell('Buy milk');

		$this->prepare(ActivityManager::SUBJECT_ROW_UPDATE, $this->viewParams(self::TITLE_COLUMN_ID));

		$this->assertSame('Buy milk', $this->richParams['row']['name']);
	}

	public function testRowFilteredOutOfViewDoesNotExposeValue(): void {
		$this->permissionsService->method('canAccessNodeById')->willReturn(true);
		$this->rowVisibilityService = $this->createMock(RowVisibilityService::class);
		$this->rowVisibilityService->method('usersSeeingRow')->willReturn([]);
		$this->rebuildNotifier();
		$this->mockView([self::TITLE_COLUMN_ID]);
		$this->columnMapper->expects($this->never())->method('find');
		$this->row2Mapper->expects($this->never())->method('find');

		$this->prepare(ActivityManager::SUBJECT_ROW_ASSIGN, $this->viewParams(self::TITLE_COLUMN_ID));

		$this->assertSame('#42', $this->richParams['row']['name']);
		$this->assertSame('You have been assigned in row #42 of view View by .', $this->parsedSubject);
	}

	private function prepare(string $subject, array $params): void {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn('tables');
		$notification->method('getObjectType')->willReturn(ActivityManager::TABLES_OBJECT_ROW);
		$notification->method('getSubject')->willReturn($subject);
		$notification->method('getSubjectParameters')->willReturn($params);
		$notification->method('getUser')->willReturn(self::RECIPIENT);
		$notification->method('setIcon')->willReturnSelf();
		$notification->method('setLink')->willReturnSelf();
		$notification->method('setParsedSubject')->willReturnCallback(function (string $parsed) use ($notification): INotification {
			$this->parsedSubject = $parsed;
			return $notification;
		});
		$notification->method('setRichSubject')->willReturnCallback(function (string $rich, array $richParams) use ($notification): INotification {
			$this->richSubject = $rich;
			$this->richParams = $richParams;
			return $notification;
		});

		$this->notifier->prepare($notification, 'en');
	}

	private function tableParams(): array {
		return [
			'objectType' => ActivityManager::TABLES_OBJECT_ROW,
			'table' => ['id' => self::TABLE_ID, 'title' => 'Table'],
			'row' => ['id' => self::ROW_ID],
			'isViewContext' => false,
		];
	}

	private function viewParams(?int $labelColumnId): array {
		return array_merge($this->tableParams(), [
			'isViewContext' => true,
			'view' => ['id' => self::VIEW_ID, 'title' => 'View', 'labelColumnId' => $labelColumnId],
		]);
	}

	private function mockView(array $columnIds): void {
		$view = new View();
		$view->setId(self::VIEW_ID);
		$view->setColumnsArray($columnIds);
		$this->viewService->method('find')->willReturn($view);
	}

	private function titleColumn(): Column {
		$column = new Column();
		$column->setId(self::TITLE_COLUMN_ID);
		$column->setType(Column::TYPE_TEXT);
		return $column;
	}

	private function mockTitleCell(string $value): void {
		$this->columnMapper->method('find')->willReturn($this->titleColumn());
		$row = new Row2();
		$row->setId(self::ROW_ID);
		$row->addCell(self::TITLE_COLUMN_ID, $value);
		$this->row2Mapper->method('find')->willReturn($row);
	}
}
