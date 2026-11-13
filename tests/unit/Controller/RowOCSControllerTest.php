<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\RowOCSController;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RowOCSControllerTest extends TestCase {
	private const UUID = '0199e3a0-1f2b-7c3d-8e4f-5a6b7c8d9e0f';

	private RowService&MockObject $rowService;
	private TableService&MockObject $tableService;
	private FederationService&MockObject $federationService;
	private RowOCSController $controller;

	protected function setUp(): void {
		$this->rowService = $this->createMock(RowService::class);
		$this->tableService = $this->createMock(TableService::class);
		$this->federationService = $this->createMock(FederationService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->controller = new RowOCSController(
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$l10n,
			'user1',
			$this->rowService,
			$this->tableService,
			$this->createMock(ViewService::class),
			$this->federationService,
		);
	}

	private function row(int $rowId): Row2 {
		$row = new Row2();
		$row->setId($rowId);
		$row->setUuid(self::UUID);
		$row->setTableId(3);
		return $row;
	}

	public function testGetRowResolvesTheIdentifierWithinTheTable(): void {
		$this->rowService->expects($this->once())
			->method('resolveRowId')
			->with(self::UUID, 3, null)
			->willReturn(7);
		$this->rowService->expects($this->once())
			->method('findInNode')
			->with(7, 3, null, 'user1')
			->willReturn($this->row(7));

		$response = $this->controller->getRow('tables', 3, self::UUID);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(self::UUID, $response->getData()['uuid']);
	}

	public function testGetRowResolvesTheIdentifierWithinTheView(): void {
		$this->rowService->expects($this->once())
			->method('resolveRowId')
			->with('7', null, 5)
			->willReturn(7);
		$this->rowService->method('findInNode')->with(7, null, 5, 'user1')->willReturn($this->row(7));

		$this->assertSame(Http::STATUS_OK, $this->controller->getRow('views', 5, '7')->getStatus());
	}

	public function testGetRowReturnsNotFoundForAUuidOfAnotherTable(): void {
		$this->rowService->method('resolveRowId')->willThrowException(new NotFoundError('Row not found.'));

		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller->getRow('tables', 3, self::UUID)->getStatus());
	}

	public function testGetRowIsNotSupportedOnAFederatedTable(): void {
		$this->federationService->method('isNodeFederated')->with(3, 'table')->willReturn(true);
		$this->rowService->expects($this->never())->method('findInNode');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller->getRow('tables', 3, '7')->getStatus());
	}

	public function testUpdateRowUsesTheResolvedRowId(): void {
		$this->rowService->method('resolveRowId')->with(self::UUID, 3, null)->willReturn(7);
		$this->rowService->expects($this->once())
			->method('updateSet')
			->with(7, null, [1 => 'value'], 'user1', 3)
			->willReturn($this->row(7));

		$response = $this->controller->updateRow('tables', 3, self::UUID, [1 => 'value']);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateRowRejectsAnInvalidIdentifier(): void {
		$this->rowService->method('resolveRowId')->willThrowException(new BadRequestError('invalid'));
		$this->rowService->expects($this->never())->method('updateSet');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller->updateRow('tables', 3, 'not-a-row', [])->getStatus());
	}

	public function testUpdateRowForwardsAnIntegerIdToAFederatedTable(): void {
		$table = new Table();
		$this->federationService->method('isNodeFederated')->willReturn(true);
		$this->tableService->method('find')->willReturn($table);
		$this->federationService->expects($this->once())
			->method('updateRow')
			->with($table, 7, [1 => 'value'])
			->willReturn(['id' => 7]);

		$this->assertSame(Http::STATUS_OK, $this->controller->updateRow('tables', 3, '7', [1 => 'value'])->getStatus());
	}

	public function testUpdateRowRejectsAUuidOnAFederatedTable(): void {
		$this->federationService->method('isNodeFederated')->willReturn(true);
		$this->tableService->method('find')->willReturn(new Table());
		$this->federationService->expects($this->never())->method('updateRow');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller->updateRow('tables', 3, self::UUID, [])->getStatus());
	}

	public function testDeleteRowUsesTheResolvedRowId(): void {
		$this->rowService->method('resolveRowId')->with(self::UUID, null, 5)->willReturn(7);
		$this->rowService->expects($this->once())
			->method('delete')
			->with(7, 5, 'user1', null)
			->willReturn($this->row(7));

		$this->assertSame(Http::STATUS_OK, $this->controller->deleteRow('views', 5, self::UUID)->getStatus());
	}

	public function testDeleteRowRejectsAUuidOnAFederatedTable(): void {
		$this->federationService->method('isNodeFederated')->willReturn(true);
		$this->tableService->method('find')->willReturn(new Table());
		$this->federationService->expects($this->never())->method('deleteRow');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller->deleteRow('tables', 3, self::UUID)->getStatus());
	}
}
