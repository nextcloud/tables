<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\PublicRowOCSController;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\Share;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PublicRowOCSControllerTest extends TestCase {
	private const TOKEN = 'abcdefgh12345678';
	private const UUID = '0199e3a0-1f2b-7c3d-8e4f-5a6b7c8d9e0f';

	private RowService&MockObject $rowService;
	private ShareService&MockObject $shareService;
	private PublicRowOCSController $controller;

	protected function setUp(): void {
		$this->rowService = $this->createMock(RowService::class);
		$this->rowService->method('formatRowsForPublicShare')->willReturnCallback(
			static fn (array $rows): array => array_map(static fn (Row2 $row): array => $row->jsonSerialize(), $rows),
		);
		$this->shareService = $this->createMock(ShareService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->controller = new PublicRowOCSController(
			$this->shareService,
			$this->rowService,
			$this->createMock(Row2Mapper::class),
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$l10n,
		);
	}

	private function shareOf(string $nodeType, int $nodeId): void {
		$share = new Share();
		$share->setNodeType($nodeType);
		$share->setNodeId($nodeId);
		$share->setPermissionUpdate(true);
		$share->setPermissionDelete(true);
		$this->shareService->method('findByToken')->willReturn($share);
	}

	private function row(int $rowId): Row2 {
		$row = new Row2();
		$row->setId($rowId);
		$row->setUuid(self::UUID);
		$row->setTableId(3);
		return $row;
	}

	public function testUpdateRowResolvesTheIdentifierWithinTheSharedTable(): void {
		$this->shareOf('table', 3);
		$this->rowService->expects($this->once())
			->method('resolveRowId')
			->with(self::UUID, 3, null)
			->willReturn(7);
		$this->rowService->expects($this->once())
			->method('updateSet')
			->with(7, null, [], '', 3)
			->willReturn($this->row(7));

		$response = $this->controller->updateRow(self::TOKEN, self::UUID, []);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(self::UUID, $response->getData()['uuid']);
	}

	public function testDeleteRowResolvesTheIdentifierWithinTheSharedView(): void {
		$this->shareOf('view', 5);
		$this->rowService->expects($this->once())
			->method('resolveRowId')
			->with(self::UUID, null, 5)
			->willReturn(7);
		$this->rowService->expects($this->once())
			->method('delete')
			->with(7, 5, '', null)
			->willReturn($this->row(7));

		$response = $this->controller->deleteRow(self::TOKEN, self::UUID);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testDeleteRowRejectsAMalformedIdentifier(): void {
		$this->shareOf('table', 3);
		$this->rowService->method('resolveRowId')
			->willThrowException(new BadRequestError('The row identifier is neither a row id nor a uuid.'));
		$this->rowService->expects($this->never())->method('delete');

		$response = $this->controller->deleteRow(self::TOKEN, 'not-a-row');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testUpdateRowDoesNotFindAUuidOutsideTheShare(): void {
		$this->shareOf('table', 3);
		$this->rowService->method('resolveRowId')->willThrowException(new NotFoundError('Row not found.'));
		$this->rowService->expects($this->never())->method('updateSet');

		$response = $this->controller->updateRow(self::TOKEN, self::UUID, []);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
