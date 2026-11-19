<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\MenuController;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Model\MenuOrderEntry;
use OCA\Tables\Model\MenuTarget;
use OCA\Tables\Service\MenuService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MenuControllerTest extends TestCase {
	private MenuController $controller;
	private MenuService&MockObject $menuService;

	protected function setUp(): void {
		parent::setUp();
		$this->menuService = $this->createMock(MenuService::class);
		$this->controller = new MenuController(
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			'alice',
			$this->menuService,
		);
	}

	public static function endpoints(): array {
		$target = ['url' => 'https://nextcloud.com'];
		$order = [['id' => 3, 'parentId' => null]];
		return [
			'index' => ['findAll', [7, 'alice'], static fn (MenuController $controller) => $controller->index(7)],
			'create' => ['create', [7, 'Site', 'star', 'Ours', MenuTarget::fromArray($target), 'alice'], static fn (MenuController $controller) => $controller->create(7, 'Site', 'star', 'Ours', $target)],
			'order' => ['saveOrder', [7, [new MenuOrderEntry(3, null)], 'alice'], static fn (MenuController $controller) => $controller->order(7, $order)],
			'update' => ['update', [7, 3, '', null, null, MenuTarget::fromArray($target), 'alice'], static fn (MenuController $controller) => $controller->update(7, 3, '', null, null, $target)],
			'destroy' => ['delete', [7, 3, 'alice'], static fn (MenuController $controller) => $controller->destroy(7, 3)],
		];
	}

	/**
	 * @dataProvider endpoints
	 */
	public function testAnEndpointPassesItsArgumentsOn(string $method, array $arguments, \Closure $call): void {
		$this->menuService->expects($this->once())->method($method)->with(...$arguments)->willReturn([]);

		$this->assertSame(Http::STATUS_OK, $call($this->controller)->getStatus());
	}

	public static function errors(): array {
		$errors = [];
		foreach (self::endpoints() as $endpoint => [$method, , $call]) {
			$errors[$endpoint . ', not found'] = [$method, $call, new NotFoundError('Menu item 3 does not exist'), Http::STATUS_NOT_FOUND];
			if ($endpoint !== 'index') {
				$errors[$endpoint . ', no permission'] = [$method, $call, new PermissionError('User bob cannot manage application 7'), Http::STATUS_FORBIDDEN];
			}
			if ($endpoint !== 'index' && $endpoint !== 'destroy') {
				$errors[$endpoint . ', bad request'] = [$method, $call, new BadRequestError('A group needs a title.'), Http::STATUS_BAD_REQUEST];
			}
		}
		return $errors;
	}

	/**
	 * @dataProvider errors
	 */
	public function testAnErrorIsAnsweredWithItsStatus(string $method, \Closure $call, \Exception $error, int $status): void {
		$this->menuService->method($method)->willThrowException($error);

		$response = $call($this->controller);

		$this->assertSame($status, $response->getStatus());
		$this->assertArrayHasKey('message', $response->getData());
	}

	public static function malformedBodies(): array {
		return [
			'create, two targets' => [static fn (MenuController $controller) => $controller->create(7, 'Site', null, null, ['url' => 'https://nextcloud.com', 'pageId' => 3])],
			'create, a page id as text' => [static fn (MenuController $controller) => $controller->create(7, 'Site', null, null, ['pageId' => '3'])],
			'update, an unknown target' => [static fn (MenuController $controller) => $controller->update(7, 3, null, null, null, ['tableId' => 3])],
			'update, a javascript link' => [static fn (MenuController $controller) => $controller->update(7, 3, null, null, null, ['url' => 'javascript:alert(1)'])],
			'order, an object instead of a list' => [static fn (MenuController $controller) => $controller->order(7, ['a' => ['id' => 3, 'parentId' => null]])],
			'order, an entry without an id' => [static fn (MenuController $controller) => $controller->order(7, [['parentId' => null]])],
		];
	}

	/**
	 * @dataProvider malformedBodies
	 */
	public function testAMalformedBodyIsRefusedBeforeTheServiceRuns(\Closure $call): void {
		$this->menuService->expects($this->never())->method($this->anything());

		$this->assertSame(Http::STATUS_BAD_REQUEST, $call($this->controller)->getStatus());
	}
}
