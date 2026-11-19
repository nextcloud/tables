<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Api\V1Api;
use OCA\Tables\Controller\Api1Controller;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\ImportService;
use OCA\Tables\Service\RelationService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class Api1ControllerUpdateRowTest extends TestCase {
	private RowService&MockObject $rowService;
	private Api1Controller $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->rowService = $this->createMock(RowService::class);
		$this->controller = new Api1Controller(
			$this->createMock(IRequest::class),
			$this->createMock(TableService::class),
			$this->createMock(ShareService::class),
			$this->createMock(ColumnService::class),
			$this->rowService,
			$this->createMock(ImportService::class),
			$this->createMock(ViewService::class),
			$this->createMock(RelationService::class),
			$this->createMock(ViewMapper::class),
			$this->createMock(V1Api::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			'alice',
			$this->createMock(FederationService::class),
		);
	}

	public function testARowOutsideTheViewIsNotFoundWithAMessage(): void {
		$this->rowService->method('updateSet')->willThrowException(new NotFoundError('Row not found.'));

		$response = $this->controller->updateRow(12, 6, ['3' => 'blue']);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['message' => 'Row not found.'], $response->getData());
	}

	public function testARowWithoutUpdatePermissionIsForbiddenWithAMessage(): void {
		$this->rowService->method('updateSet')->willThrowException(new PermissionError('Update row is not allowed.'));

		$response = $this->controller->updateRow(12, 6, ['3' => 'blue']);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['message' => 'Update row is not allowed.'], $response->getData());
	}
}
