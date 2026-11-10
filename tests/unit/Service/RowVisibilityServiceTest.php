<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\View;
use OCA\Tables\Service\RowVisibilityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RowVisibilityServiceTest extends TestCase {
	private const ROW_ID = 42;

	private Row2Mapper&MockObject $row2Mapper;
	private RowVisibilityService $service;
	private View $view;

	protected function setUp(): void {
		parent::setUp();

		$this->row2Mapper = $this->createMock(Row2Mapper::class);
		$this->service = new RowVisibilityService($this->row2Mapper);

		$this->view = new View();
		$this->view->setId(7);
		$this->view->setTableId(1);
		$this->view->setFilter('[[{"columnId":-2,"operator":"is-equal","value":"@me"}]]');
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
