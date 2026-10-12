<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Helper\UserHelper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Row2MapperViewWithoutTableTest extends TestCase {
	private Row2Mapper $mapper;

	protected function setUp(): void {
		parent::setUp();

		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->never())->method('getQueryBuilder');
		$rowSleeveMapper = $this->createMock(RowSleeveMapper::class);
		$rowSleeveMapper->expects($this->never())->method('countRows');

		$this->mapper = new Row2Mapper(
			'alice',
			$db,
			$this->createMock(LoggerInterface::class),
			$this->createMock(UserHelper::class),
			$rowSleeveMapper,
			$this->createMock(ColumnsHelper::class),
			$this->createMock(ColumnMapper::class),
		);
	}

	private function viewWithoutTable(): View {
		$view = new View();
		$view->setId(6);
		$view->setTableId(null);
		$view->setType(View::TYPE_GRID);
		return $view;
	}

	public function testNoRowIsPresentInAViewWithoutATable(): void {
		$this->assertFalse($this->mapper->isRowInViewPresent(12, $this->viewWithoutTable(), 'alice'));
	}

	public function testAViewWithoutATableCountsNoRows(): void {
		$this->assertSame(0, $this->mapper->countRowsForView($this->viewWithoutTable(), 'alice'));
	}
}
