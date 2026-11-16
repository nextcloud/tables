<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\RowVisibilityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RowVisibilityServiceTest extends TestCase {
	private const ROW_ID = 42;
	private const VIEW_ID = 7;

	private Row2Mapper&MockObject $row2Mapper;
	private ViewMapper&MockObject $viewMapper;
	private RowVisibilityService $service;
	private View $view;

	protected function setUp(): void {
		parent::setUp();

		$this->row2Mapper = $this->createMock(Row2Mapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->service = new RowVisibilityService($this->row2Mapper, $this->viewMapper);

		$this->view = new View();
		$this->view->setId(self::VIEW_ID);
		$this->view->setTableId(1);
		$this->view->setFilter('[[{"columnId":-2,"operator":"is-equal","value":"@me"}]]');
	}

	public function testUserSeesRowInView(): void {
		$this->viewMapper->expects($this->once())
			->method('find')
			->with(self::VIEW_ID)
			->willReturn($this->view);
		$this->row2Mapper->expects($this->once())
			->method('isRowInViewPresent')
			->with(self::ROW_ID, $this->view, 'alice')
			->willReturn(true);

		$this->assertTrue($this->service->userSeesRowWithViewId(self::ROW_ID, self::VIEW_ID, 'alice'));
	}

	public function testUserDoesNotSeeRowInView(): void {
		$this->viewMapper->expects($this->once())
			->method('find')
			->with(self::VIEW_ID)
			->willReturn($this->view);
		$this->row2Mapper->expects($this->once())
			->method('isRowInViewPresent')
			->with(self::ROW_ID, $this->view, 'mallory')
			->willReturn(false);

		$this->assertFalse($this->service->userSeesRowWithViewId(self::ROW_ID, self::VIEW_ID, 'mallory'));
	}

	public function testKnownUsersAreNotResolvedAgain(): void {
		$this->row2Mapper->expects($this->once())
			->method('filterUsersSeeingRow')
			->with(self::ROW_ID, $this->view, ['alice', 'bob', 'carol'])
			->willReturn(['alice', 'carol']);

		$this->assertSame(['alice', 'carol'], $this->service->usersSeeingRow(self::ROW_ID, $this->view, ['alice', 'bob', 'carol']));
		$this->assertSame(['carol'], $this->service->usersSeeingRow(self::ROW_ID, $this->view, ['bob', 'carol']));
	}

	public function testOnlyUnknownUsersAreResolved(): void {
		$this->row2Mapper->expects($this->exactly(2))
			->method('filterUsersSeeingRow')
			->willReturnCallback(fn (int $rowId, View $view, array $userIds): array => match ($userIds) {
				['alice'] => ['alice'],
				['bob'] => [],
			});

		$this->service->usersSeeingRow(self::ROW_ID, $this->view, ['alice']);

		$this->assertSame(['alice'], $this->service->usersSeeingRow(self::ROW_ID, $this->view, ['alice', 'bob']));
	}

	public function testForgetResolvesTheRowAgain(): void {
		$this->row2Mapper->expects($this->exactly(2))
			->method('filterUsersSeeingRow')
			->willReturnOnConsecutiveCalls(['alice'], []);

		$this->service->usersSeeingRow(self::ROW_ID, $this->view, ['alice']);
		$this->service->forget(self::ROW_ID);

		$this->assertSame([], $this->service->usersSeeingRow(self::ROW_ID, $this->view, ['alice']));
	}

	public function testChangedFilterResolvesTheRowAgain(): void {
		$this->row2Mapper->expects($this->exactly(2))
			->method('filterUsersSeeingRow')
			->willReturnOnConsecutiveCalls(['alice'], []);

		$this->service->usersSeeingRow(self::ROW_ID, $this->view, ['alice']);
		$this->view->setFilter('[]');

		$this->assertSame([], $this->service->usersSeeingRow(self::ROW_ID, $this->view, ['alice']));
	}

	public function testNumericUserIdsAreHandledAsStrings(): void {
		$this->row2Mapper->method('filterUsersSeeingRow')->willReturn(['123']);

		$this->assertSame(['123'], $this->service->usersSeeingRow(self::ROW_ID, $this->view, [123, 'bob']));
	}
}
