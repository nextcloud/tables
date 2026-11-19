<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Controller\MenuController;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\ConflictError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Model\MenuOrderEntry;
use OCA\Tables\Model\MenuTarget;
use OCA\Tables\Service\MenuService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MenuControllerTest extends TestCase {
	private MenuController $controller;
	private MenuService&MockObject $menuService;
	private string $ifMatch = '"v1"';

	protected function setUp(): void {
		parent::setUp();
		$this->menuService = $this->createMock(MenuService::class);
		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->willReturnCallback(fn (string $name): string => $name === 'If-Match' ? $this->ifMatch : '');
		$this->controller = new MenuController(
			$request,
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			'alice',
			$this->menuService,
		);
	}

	public static function endpoints(): array {
		$target = ['url' => 'https://nextcloud.com'];
		$order = [['id' => 3, 'parentId' => null]];
		$menu = ['menu' => [], 'version' => 'v2'];
		return [
			'index' => ['findAll', [7, 'alice'], static fn (MenuController $controller) => $controller->index(7), $menu],
			'create' => ['create', [7, 'Site', 'star', 'Ours', MenuTarget::fromArray($target), 'alice'], static fn (MenuController $controller) => $controller->create(7, 'Site', 'star', 'Ours', $target)],
			'order' => ['saveOrder', [7, [new MenuOrderEntry(3, null)], 'v1', 'alice'], static fn (MenuController $controller) => $controller->order(7, $order), $menu],
			'update' => ['update', [7, 3, '', null, null, MenuTarget::fromArray($target), 'alice'], static fn (MenuController $controller) => $controller->update(7, 3, '', null, null, $target)],
			'destroy' => ['delete', [7, 3, 'alice'], static fn (MenuController $controller) => $controller->destroy(7, 3)],
		];
	}

	/**
	 * @dataProvider endpoints
	 */
	public function testAnEndpointPassesItsArgumentsOn(string $method, array $arguments, \Closure $call, array $result = []): void {
		$this->menuService->expects($this->once())->method($method)->with(...$arguments)->willReturn($result);

		$this->assertSame(Http::STATUS_OK, $call($this->controller)->getStatus());
	}

	public function testTheMenuAndASavedOrderCarryTheVersionAsETag(): void {
		$this->menuService->method('findAll')->willReturn(['menu' => [['id' => 3]], 'version' => 'v1']);
		$this->menuService->method('saveOrder')->willReturn(['menu' => [['id' => 3]], 'version' => 'v2']);

		$index = $this->controller->index(7);
		$order = $this->controller->order(7, [['id' => 3, 'parentId' => null]]);

		$this->assertSame([[['id' => 3]], '"v1"'], [$index->getData(), $index->getHeaders()['ETag']]);
		$this->assertSame([[['id' => 3]], '"v2"'], [$order->getData(), $order->getHeaders()['ETag']]);
	}

	public static function ifMatchHeaders(): array {
		return [
			'none' => [''],
			'without quotes' => ['v1'],
			'weak' => ['W/"v1"'],
			'any' => ['*'],
			'two tags' => ['"v1", "v2"'],
		];
	}

	/**
	 * @dataProvider ifMatchHeaders
	 */
	public function testAnOrderSaveWithoutOneStrongETagInIfMatchIsRefused(string $ifMatch): void {
		$this->ifMatch = $ifMatch;
		$this->menuService->expects($this->never())->method('saveOrder');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller->order(7, [['id' => 3, 'parentId' => null]])->getStatus());
	}

	public function testAnOrderSaveOnAnotherVersionIsAConflict(): void {
		$this->menuService->method('saveOrder')->willThrowException(new ConflictError('The menu was changed by another save in the meantime.'));

		$this->assertSame(Http::STATUS_CONFLICT, $this->controller->order(7, [['id' => 3, 'parentId' => null]])->getStatus());
	}

	public static function errors(): array {
		$errors = [];
		foreach (self::endpoints() as $endpoint => [$method, , $call]) {
			$errors[$endpoint . ', not found'] = [$method, $call, new NotFoundError('Menu item 3 does not exist'), Http::STATUS_NOT_FOUND];
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

	public static function permissions(): array {
		return [
			'index' => ['index', Application::PERMISSION_READ],
			'create' => ['create', Application::PERMISSION_MANAGE],
			'order' => ['order', Application::PERMISSION_MANAGE],
			'update' => ['update', Application::PERMISSION_MANAGE],
			'destroy' => ['destroy', Application::PERMISSION_MANAGE],
		];
	}

	/**
	 * @dataProvider permissions
	 */
	public function testAnEndpointRequiresItsPermissionOnTheApplication(string $method, int $permission): void {
		$attributes = (new \ReflectionMethod(MenuController::class, $method))->getAttributes(RequirePermission::class);

		$this->assertCount(1, $attributes);
		$attribute = $attributes[0]->newInstance();
		$this->assertSame([$permission, 'context', 'contextId'], [$attribute->getPermission(), $attribute->getTypeParam(), $attribute->getIdParam()]);
	}

	/**
	 * @dataProvider permissions
	 */
	public function testEveryWriteIsRateLimited(string $method, int $permission): void {
		$attributes = (new \ReflectionMethod(MenuController::class, $method))->getAttributes(UserRateLimit::class);

		if ($permission === Application::PERMISSION_READ) {
			$this->assertSame([], $attributes);
			return;
		}
		$this->assertCount(1, $attributes);
		$this->assertSame(['limit' => 20, 'period' => 60], $attributes[0]->getArguments());
	}
}
