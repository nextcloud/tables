<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\ContextController;
use OCA\Tables\Db\Context;
use OCA\Tables\Model\MenuItemInput;
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

final class ContextControllerTest extends TestCase {
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

	public function testCreatePassesAPageItemWithoutAnIdOnAsTheNewStartPage(): void {
		$context = new Context();
		$context->setMenuItems([]);
		$this->contextService->expects($this->once())->method('create')
			->with('Intake', 'star', '', [], 'user-1', 0, $this->callback(static fn (array $menuItems): bool => count($menuItems) === 1
					&& $menuItems[0] instanceof MenuItemInput
					&& $menuItems[0]->pointsAtNewStartPage()
					&& $menuItems[0]->getLabel() === 'Home'))
			->willReturn($context);

		$response = $this->controller->create('Intake', 'star', '', [], [['label' => 'Home', 'targetType' => 'page']]);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateRefusesAPageItemWithoutAnId(): void {
		$this->contextService->expects($this->never())->method('update');

		$response = $this->controller->update(7, null, null, null, null, [['label' => 'Home', 'targetType' => 'page']]);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertStringContainsString('needs its id', $response->getData()['message']);
	}

	public function testCreateWithoutAMenuLeavesItToTheService(): void {
		$context = new Context();
		$context->setMenuItems([]);
		$this->contextService->expects($this->once())->method('create')
			->with('Intake', 'star', '', [], 'user-1', 0, null)
			->willReturn($context);

		$this->assertSame(Http::STATUS_OK, $this->controller->create('Intake', 'star')->getStatus());
	}

	public static function invalidMenus(): array {
		return [
			'not a list' => [['home' => ['label' => 'Home', 'url' => 'https://example.org']], 'must be a list'],
			'javascript link' => [[['label' => 'X', 'url' => 'javascript:alert(1)']], 'http or https'],
			'unknown icon' => [[['label' => 'X', 'url' => 'https://example.org', 'icon' => 'no-such-icon']], 'unknown icon'],
		];
	}

	/**
	 * @dataProvider invalidMenus
	 */
	public function testAnInvalidMenuIsABadRequest(array $menuItems, string $message): void {
		$this->contextService->expects($this->never())->method('create');
		$this->contextService->expects($this->never())->method('update');

		foreach ([$this->controller->create('Intake', 'star', '', [], $menuItems), $this->controller->update(7, null, null, null, null, $menuItems)] as $response) {
			$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
			$this->assertStringContainsString($message, $response->getData()['message']);
		}
	}

	public function testUpdatePassesTheMenu(): void {
		$context = new Context();
		$context->setMenuItems([]);
		$this->contextService->expects($this->once())->method('update')
			->with(7, 'user-1', null, null, null, null, $this->callback(static fn (array $menuItems): bool => count($menuItems) === 1 && $menuItems[0]->getTargetId() === 5))
			->willReturn($context);

		$response = $this->controller->update(7, null, null, null, null, [['label' => 'Overview', 'targetType' => 'page', 'targetId' => 5]]);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testATakenTechnicalNameIsABadRequest(): void {
		$this->contextService->method('update')->willThrowException(new \InvalidArgumentException('The technical name docs is already used by a menu item of this application.'));

		$response = $this->controller->update(7, null, null, null, null, [['label' => 'Docs', 'url' => 'https://example.org', 'technicalName' => 'docs']]);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}
}
