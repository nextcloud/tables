<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\Tables\Controller\ContextController;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ContextControllerMenuItemsTest extends TestCase {
	private ContextController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->controller = new class($this->createMock(IRequest::class), $this->createMock(LoggerInterface::class), $this->createMock(IL10N::class), 'alice', $this->createMock(ContextService::class), $this->createMock(TableService::class), $this->createMock(IDBConnection::class), $this->createMock(ColumnService::class), $this->createMock(ViewService::class), ) extends ContextController {
			public function sanitize(array $menuItems): array {
				return $this->sanitizeInputMenuItems($menuItems);
			}
		};
	}

	public function testSanitizeCastsAndNormalisesEveryItem(): void {
		$sanitized = $this->controller->sanitize([
			['label' => 'Intake home', 'targetType' => 'view', 'targetId' => '6', 'technicalName' => 'home', 'url' => 'ignored'],
			['label' => 'Docs', 'targetType' => 'url', 'url' => 'https://example.org', 'targetId' => '99', 'icon' => 'book'],
			['label' => 'Welcome', 'targetType' => 'table', 'targetId' => 4],
		]);

		$this->assertSame([
			['label' => 'Intake home', 'icon' => null, 'targetType' => 'view', 'targetId' => 6, 'url' => null, 'technicalName' => 'home'],
			['label' => 'Docs', 'icon' => 'book', 'targetType' => 'url', 'targetId' => null, 'url' => 'https://example.org', 'technicalName' => null],
			['label' => 'Welcome', 'icon' => null, 'targetType' => 'table', 'targetId' => 4, 'url' => null, 'technicalName' => null],
		], $sanitized);
	}

	public function testSanitizeRejectsAnItemWithoutLabel(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('needs a label');
		$this->controller->sanitize([['label' => '  ', 'targetType' => 'url', 'url' => 'https://example.org']]);
	}

	public function testSanitizeRejectsAnUnknownTargetType(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('target type');
		$this->controller->sanitize([['label' => 'X', 'targetType' => 'kanban', 'targetId' => 1]]);
	}

	public function testSanitizeRejectsAViewItemWithoutId(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('needs its id');
		$this->controller->sanitize([['label' => 'X', 'targetType' => 'view']]);
	}

	public function testSanitizeRejectsANonArrayItem(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->controller->sanitize(['not an item']);
	}
}
