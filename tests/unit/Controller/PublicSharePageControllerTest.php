<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Controller\PublicSharePageController;
use OCA\Tables\Db\Share;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Service\NodeService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

final class PublicSharePageControllerTest extends TestCase {
	public function testAuthFailedLogsCriticalActionWithoutToken(): void {
		$share = new Share();
		$share->setId(12);
		$share->setNodeType('table');
		$share->setNodeId(5);
		$share->setToken('abcdefghijklmnop');

		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->with('token')->willReturn('abcdefghijklmnop');
		$shareService = $this->createMock(ShareService::class);
		$shareService->method('findByToken')->willReturn($share);
		$shareService->method('isPublicShareAccessible')->willReturn(true);
		$auditLogService = $this->createMock(AuditLogServiceInterface::class);

		$controller = new PublicSharePageController(
			'tables',
			$request,
			$this->createMock(ISession::class),
			$this->createMock(IURLGenerator::class),
			$shareService,
			$this->createMock(NodeService::class),
			$this->createMock(IInitialState::class),
			$this->createMock(IEventDispatcher::class),
			$auditLogService,
		);

		$auditLogService->expects($this->once())
			->method('log')
			->with(Operation::LinkShareAuthenticationFailed, 'Wrong password entered for link share %s of %s %s', [
				'shareId' => 12,
				'nodeType' => 'table',
				'nodeId' => 5,
			]);

		$authFailed = new \ReflectionMethod($controller, 'authFailed');
		$authFailed->invoke($controller);
	}

	public function testAuthFailedDoesNotLogWithoutShare(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->with('token')->willReturn('abcdefghijklmnop');
		$shareService = $this->createMock(ShareService::class);
		$shareService->method('findByToken')->willThrowException(new NotFoundError());
		$auditLogService = $this->createMock(AuditLogServiceInterface::class);

		$controller = new PublicSharePageController(
			'tables',
			$request,
			$this->createMock(ISession::class),
			$this->createMock(IURLGenerator::class),
			$shareService,
			$this->createMock(NodeService::class),
			$this->createMock(IInitialState::class),
			$this->createMock(IEventDispatcher::class),
			$auditLogService,
		);

		$auditLogService->expects($this->never())->method('log');

		$authFailed = new \ReflectionMethod($controller, 'authFailed');
		$authFailed->invoke($controller);
	}
}
