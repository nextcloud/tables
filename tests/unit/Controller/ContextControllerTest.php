<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\ContextController;
use OCA\Tables\Db\Context;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
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
	public function testASchemeImportAnswersWithTheMenuItemsItSkipped(): void {
		$context = new Context();
		$context->setName('Intake');
		$skipped = [['index' => 2, 'title' => 'Elsewhere', 'reason' => 'Its table x is not part of the application.']];
		$contextService = $this->createMock(ContextService::class);
		$contextService->method('importScheme')->willReturn(['context' => $context, 'menuSkipped' => $skipped]);
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('commit');

		$response = $this->controller($contextService, $db)->importScheme(7, 'Intake', 'star', '', [], ['addTables' => [], 'modifyTables' => []], []);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['Intake', $skipped], [$response->getData()['name'], $response->getData()['menuSkipped']]);
	}

	public static function refusedImports(): array {
		return [
			'a bad request' => [new BadRequestError('Invalid tables structure provided.'), Http::STATUS_BAD_REQUEST],
			'something not found' => [new NotFoundError('Application 7 does not exist'), Http::STATUS_NOT_FOUND],
			'an internal error' => [new InternalError('The view could not be saved.'), Http::STATUS_INTERNAL_SERVER_ERROR],
		];
	}

	/**
	 * @dataProvider refusedImports
	 */
	public function testARefusedSchemeImportAnswersItsStatusAndRollsBack(\Exception $error, int $status): void {
		$contextService = $this->createMock(ContextService::class);
		$contextService->method('importScheme')->willThrowException($error);
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('rollBack');
		$db->expects($this->never())->method('commit');

		$response = $this->controller($contextService, $db)->importScheme(7, 'Intake', 'star', '', [], ['addTables' => [], 'modifyTables' => []]);

		$this->assertSame($status, $response->getStatus());
	}

	public function testASchemeImportThatIsRefusedAnswers400AndRollsBack(): void {
		$contextService = $this->createMock(ContextService::class);
		$contextService->method('importScheme')->willThrowException(new BadRequestError('The table to modify is not here.'));
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('rollBack');

		$response = $this->controller($contextService, $db)->importScheme(7, 'Intake', 'star', '', [], ['addTables' => [], 'modifyTables' => []]);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('The table to modify is not here.', $response->getData()['message']);
	}

	private function controller(ContextService $contextService, IDBConnection $db): ContextController {
		return new ContextController(
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
	}
}
