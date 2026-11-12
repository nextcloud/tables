<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\ContextController;
use OCA\Tables\Db\Context;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Http;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ContextControllerNodeInputTest extends TestCase {
	private ContextService&MockObject $contextService;
	private ContextController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->contextService = $this->createMock(ContextService::class);
		$this->controller = new ContextController(
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			'user-1',
			$this->contextService,
			$this->createMock(TableService::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(ColumnService::class),
			$this->createMock(ViewService::class),
		);
	}

	public function testCreateAnswers400ForANodeWithoutType(): void {
		$this->contextService->expects($this->never())->method('create');

		$response = $this->controller->create('Intake', 'star', '', [['id' => 5]]);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'Unexpected node type'], $response->getData());
	}

	public function testCreateAnswers400ForNodesThatAreNotAList(): void {
		$this->contextService->expects($this->never())->method('create');

		$response = $this->controller->create('Intake', 'star', '', ['a' => ['id' => 5, 'type' => 0, 'permissions' => 1]]);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'Nodes must be given as a list'], $response->getData());
	}

	public function testCreateDefaultsMissingNodePermissionsToRead(): void {
		$this->contextService->expects($this->once())
			->method('create')
			->with('Intake', 'star', '', $this->identicalTo([['id' => 5, 'type' => 0, 'permissions' => 1]]), 'user-1', 0)
			->willReturn(new Context());

		$response = $this->controller->create('Intake', 'star', '', [['id' => '5', 'type' => '0']]);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateAnswers400ForNodesThatAreNotAList(): void {
		$this->contextService->expects($this->never())->method('update');

		$response = $this->controller->update(7, null, null, null, ['a' => ['id' => 1, 'type' => 0, 'permissions' => 1]]);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'Nodes must be given as a list'], $response->getData());
	}

	public function testUpdateDefaultsMissingNodePermissionsToRead(): void {
		$this->contextService->expects($this->once())
			->method('update')
			->with(7, 'user-1', null, null, null, $this->identicalTo([['id' => 5, 'type' => 0, 'permissions' => 1]]))
			->willReturn(new Context());

		$response = $this->controller->update(7, null, null, null, [['id' => 5, 'type' => 0]]);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateKeepsTheNodesWhenNoneAreGiven(): void {
		$this->contextService->expects($this->once())
			->method('update')
			->with(7, 'user-1', 'Renamed', null, null, null)
			->willReturn(new Context());

		$response = $this->controller->update(7, 'Renamed', null, null, null);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}
}
