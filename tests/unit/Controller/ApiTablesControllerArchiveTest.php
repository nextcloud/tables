<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\ApiTablesController;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ApiTablesControllerArchiveTest extends TestCase {
	private TableService&MockObject $tableService;
	private ApiTablesController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->tableService = $this->createMock(TableService::class);

		$n = $this->createMock(IL10N::class);
		$n->method('t')->willReturnArgument(0);

		$this->controller = new ApiTablesController(
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$this->tableService,
			$this->createMock(ColumnService::class),
			$this->createMock(ViewService::class),
			$n,
			$this->createMock(IAppManager::class),
			$this->createMock(IDBConnection::class),
			'alice',
		);
	}

	// -------------------------------------------------------------------------
	// archiveTable
	// -------------------------------------------------------------------------

	public function testArchiveTableReturns200OnSuccess(): void {
		$table = $this->createTableStub(5, true);
		$this->tableService->expects($this->once())
			->method('archiveTable')
			->with(5, 'alice')
			->willReturn($table);

		$response = $this->controller->archiveTable(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(5, $response->getData()['id']);
		$this->assertTrue($response->getData()['archived']);
	}

	public function testArchiveTableReturns403OnPermissionError(): void {
		$this->tableService->method('archiveTable')
			->willThrowException(new PermissionError('no access'));

		$response = $this->controller->archiveTable(5);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testArchiveTableReturns404OnNotFoundError(): void {
		$this->tableService->method('archiveTable')
			->willThrowException(new NotFoundError('not found'));

		$response = $this->controller->archiveTable(5);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testArchiveTableReturns500OnInternalError(): void {
		$this->tableService->method('archiveTable')
			->willThrowException(new InternalError('boom'));

		$response = $this->controller->archiveTable(5);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}

	// -------------------------------------------------------------------------
	// unarchiveTable
	// -------------------------------------------------------------------------

	public function testUnarchiveTableReturns200OnSuccess(): void {
		$table = $this->createTableStub(5, false);
		$this->tableService->expects($this->once())
			->method('unarchiveTable')
			->with(5, 'alice')
			->willReturn($table);

		$response = $this->controller->unarchiveTable(5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertFalse($response->getData()['archived']);
	}

	public function testUnarchiveTableReturns403OnPermissionError(): void {
		$this->tableService->method('unarchiveTable')
			->willThrowException(new PermissionError('no access'));

		$response = $this->controller->unarchiveTable(5);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testUnarchiveTableReturns404OnNotFoundError(): void {
		$this->tableService->method('unarchiveTable')
			->willThrowException(new NotFoundError('not found'));

		$response = $this->controller->unarchiveTable(5);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUnarchiveTableReturns500OnInternalError(): void {
		$this->tableService->method('unarchiveTable')
			->willThrowException(new InternalError('boom'));

		$response = $this->controller->unarchiveTable(5);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function createTableStub(int $id, bool $archived): Table {
		$table = $this->createPartialMock(Table::class, []);
		$table->setId($id);
		$table->setArchived($archived);
		$table->setTitle('Test');
		$table->setOwnership('alice');
		return $table;
	}
}
