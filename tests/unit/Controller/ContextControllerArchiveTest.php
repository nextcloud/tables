<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\ContextController;
use OCA\Tables\Db\Context;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Service\ContextService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ContextControllerArchiveTest extends TestCase {
	private ContextService&MockObject $contextService;
	private ContextController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->contextService = $this->createMock(ContextService::class);

		$n = $this->createMock(IL10N::class);
		$n->method('t')->willReturnArgument(0);

		$this->controller = new ContextController(
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$n,
			'alice',
			$this->contextService,
		);
	}

	// -------------------------------------------------------------------------
	// archiveContext
	// -------------------------------------------------------------------------

	public function testArchiveContextReturns200OnSuccess(): void {
		$context = $this->createContextStub(3, true);
		$this->contextService->expects($this->once())
			->method('archiveContext')
			->with(3, 'alice')
			->willReturn($context);

		$response = $this->controller->archiveContext(3);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(3, $response->getData()['id']);
		$this->assertTrue($response->getData()['archived']);
	}

	public function testArchiveContextReturns404OnNotFoundError(): void {
		$this->contextService->method('archiveContext')
			->willThrowException(new NotFoundError('not found'));

		$response = $this->controller->archiveContext(3);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testArchiveContextReturns500OnInternalError(): void {
		$this->contextService->method('archiveContext')
			->willThrowException(new InternalError('boom'));

		$response = $this->controller->archiveContext(3);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}

	// -------------------------------------------------------------------------
	// unarchiveContext
	// -------------------------------------------------------------------------

	public function testUnarchiveContextReturns200OnSuccess(): void {
		$context = $this->createContextStub(3, false);
		$this->contextService->expects($this->once())
			->method('unarchiveContext')
			->with(3, 'alice')
			->willReturn($context);

		$response = $this->controller->unarchiveContext(3);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertFalse($response->getData()['archived']);
	}

	public function testUnarchiveContextReturns404OnNotFoundError(): void {
		$this->contextService->method('unarchiveContext')
			->willThrowException(new NotFoundError('not found'));

		$response = $this->controller->unarchiveContext(3);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUnarchiveContextReturns500OnInternalError(): void {
		$this->contextService->method('unarchiveContext')
			->willThrowException(new InternalError('boom'));

		$response = $this->controller->unarchiveContext(3);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function createContextStub(int $id, bool $archived): Context {
		$context = $this->createPartialMock(Context::class, []);
		$context->setId($id);
		$context->setArchived($archived);
		$context->setName('Test');
		$context->setOwnerId('alice');
		$context->setOwnerType(0);
		return $context;
	}
}
