<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\ContextController;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Http;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ContextControllerTest extends TestCase {
	public function testASchemeImportThatIsRefusedAnswers400AndRollsBack(): void {
		$contextService = $this->createMock(ContextService::class);
		$contextService->method('importScheme')->willThrowException(new BadRequestError('The table to modify is not here.'));
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('rollBack');
		$controller = new ContextController(
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			'user-1',
			$contextService,
			$this->createMock(TableService::class),
			$db,
			$this->createMock(ColumnService::class),
			$this->createMock(ViewService::class),
		);

		$response = $controller->importScheme(7, 'Intake', 'star', '', [], ['addTables' => [], 'modifyTables' => []]);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('The table to modify is not here.', $response->getData()['message']);
	}
}
