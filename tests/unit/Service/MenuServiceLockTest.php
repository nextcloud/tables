<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
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
		$service = new MenuService($contextMapper, $menuItemMapper, $permissionsService, $this->createMock(TableMapper::class), $this->createMock(ViewMapper::class), $this->createMock(IDBConnection::class));

		$this->expectException(NotFoundError::class);
		$write($service);
	}
}
