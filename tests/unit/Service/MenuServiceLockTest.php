<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\MenuItem;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Model\MenuTarget;
use OCA\Tables\Service\MenuService;
use OCA\Tables\Service\PermissionsService;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class MenuServiceLockTest extends TestCase {
	public static function writes(): array {
		return [
			'create' => [static fn (MenuService $service): array => $service->create(7, 'Site', null, null, MenuTarget::fromArray(['url' => 'https://nextcloud.com']), 'alice')],
			'update' => [static fn (MenuService $service): array => $service->update(7, 3, 'Site', null, null, null, 'alice')],
			'delete' => [static fn (MenuService $service): array => $service->delete(7, 3, 'alice')],
			'saveOrder' => [static fn (MenuService $service): array => $service->saveOrder(7, [], 'version', 'alice')],
		];
	}

	/**
	 * @dataProvider writes
	 */
	public function testAWriteToAnApplicationThatIsGoneStopsBeforeItReadsTheMenu(\Closure $write): void {
		$context = new Context();
		$context->id = 7;
		$contextMapper = $this->createMock(ContextMapper::class);
		$contextMapper->method('findById')->willReturn($context);
		$contextMapper->expects($this->once())->method('lock')->with(7)->willReturn(false);
		$permissionsService = $this->createMock(PermissionsService::class);
		$permissionsService->method('canManageContext')->willReturn(true);
		$menuItemMapper = $this->createMock(MenuItemMapper::class);
		$menuItemMapper->expects($this->never())->method($this->anything());
		$service = new MenuService($contextMapper, $this->createMock(ContextNodeRelationMapper::class), $menuItemMapper, $permissionsService, $this->createMock(TableMapper::class), $this->createMock(ViewMapper::class), $this->createMock(IDBConnection::class));

		$this->expectException(NotFoundError::class);
		$write($service);
	}

	public static function writesToATable(): array {
		$target = MenuTarget::fromArray(['nodeRelId' => 11]);
		return [
			'create' => [static fn (MenuService $service) => $service->create(7, null, null, null, $target, 'alice')],
			'update' => [static fn (MenuService $service) => $service->update(7, 3, null, null, null, $target, 'alice')],
		];
	}

	/**
	 * @dataProvider writesToATable
	 */
	public function testAWriteLocksTheTableOrViewItOpensBeforeItReadsTheApplication(\Closure $write): void {
		$calls = [];
		$contextMapper = $this->createMock(ContextMapper::class);
		$contextMapper->method('lock')->willReturnCallback(static function () use (&$calls): bool {
			$calls[] = 'lock application';
			return true;
		});
		$contextMapper->method('findById')->willReturnCallback(static function () use (&$calls): Context {
			$calls[] = 'read application';
			throw new NotFoundError('stop here');
		});
		$contextNodeRelMapper = $this->createMock(ContextNodeRelationMapper::class);
		$contextNodeRelMapper->expects($this->once())->method('lockByIds')->with([11])->willReturnCallback(static function () use (&$calls): void {
			$calls[] = 'lock table';
		});
		$menuItemMapper = $this->createMock(MenuItemMapper::class);
		$item = new MenuItem();
		$item->setContextId(7);
		$menuItemMapper->method('findById')->willReturn($item);
		$service = new MenuService($contextMapper, $contextNodeRelMapper, $menuItemMapper, $this->createMock(PermissionsService::class), $this->createMock(TableMapper::class), $this->createMock(ViewMapper::class), $this->createMock(IDBConnection::class));

		try {
			$write($service);
			$this->fail('The write went past the application read.');
		} catch (NotFoundError) {
		}
		$this->assertSame(['lock application', 'lock table', 'read application'], $calls);
	}
}
