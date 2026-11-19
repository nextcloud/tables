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
use OCA\Tables\Model\MenuOrderEntry;
use OCA\Tables\Service\MenuService;
use OCA\Tables\Service\PermissionsService;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Which writes a menu change sends to the database.
 */
class MenuServiceWritesTest extends TestCase {
	private MenuItemMapper&MockObject $menuItemMapper;
	private MenuService $service;

	protected function setUp(): void {
		parent::setUp();
		$context = new Context();
		$context->id = 7;
		$context->setName('Sales');
		$contextMapper = $this->createMock(ContextMapper::class);
		$contextMapper->method('lock')->willReturn(true);
		$contextMapper->method('findById')->willReturn($context);
		$this->menuItemMapper = $this->createMock(MenuItemMapper::class);
		$this->service = new MenuService($contextMapper, $this->createMock(ContextNodeRelationMapper::class), $this->menuItemMapper, $this->createMock(PermissionsService::class), $this->createMock(TableMapper::class), $this->createMock(ViewMapper::class), $this->createMock(IDBConnection::class));
	}

	public function testDeletingAnItemThatIsNoGroupMovesNoItems(): void {
		$item = $this->item(3, null, 0, 'https://nextcloud.com');
		$this->menuItemMapper->method('findById')->willReturn($item);

		$this->menuItemMapper->expects($this->never())->method('clearParent');
		$this->menuItemMapper->expects($this->once())->method('delete')->with($item);
		$this->service->delete(7, 3, 'alice');
	}

	public function testDeletingAGroupMovesItsItemsInItsApplication(): void {
		$this->menuItemMapper->method('findById')->willReturn($this->item(3, null, 0, null));

		$this->menuItemMapper->expects($this->once())->method('clearParent')->with(7, 3);
		$this->service->delete(7, 3, 'alice');
	}

	public function testAnOrderSaveWritesOnlyTheItemsThatMoved(): void {
		$items = [$this->item(3, null, 0, null), $this->item(4, null, 1, 'https://nextcloud.com'), $this->item(5, null, 2, 'https://example.com')];
		$this->menuItemMapper->method('findByContextId')->willReturn($items);
		$version = $this->service->findAll(7, 'alice')['version'];

		$this->menuItemMapper->expects($this->once())->method('updatePositions')->with([4 => ['order' => 1, 'parentId' => 3]]);
		$this->menuItemMapper->expects($this->never())->method('update');
		$this->service->saveOrder(7, [new MenuOrderEntry(3, null), new MenuOrderEntry(4, 3), new MenuOrderEntry(5, null)], $version, 'alice');
	}

	public function testAnUnchangedOrderWritesNothing(): void {
		$this->menuItemMapper->method('findByContextId')->willReturn([$this->item(3, null, 0, null), $this->item(4, 3, 1, 'https://nextcloud.com')]);
		$version = $this->service->findAll(7, 'alice')['version'];

		$this->menuItemMapper->expects($this->once())->method('updatePositions')->with([]);
		$this->menuItemMapper->expects($this->never())->method('update');
		$this->service->saveOrder(7, [new MenuOrderEntry(3, null), new MenuOrderEntry(4, 3)], $version, 'alice');
	}

	private function item(int $id, ?int $parentId, int $order, ?string $url): MenuItem {
		$item = new MenuItem();
		$item->id = $id;
		$item->setContextId(7);
		$item->setParentId($parentId);
		$item->setOrder($order);
		$item->setTitle('Item ' . $id);
		$item->setUrl($url);
		$item->resetUpdatedFields();
		return $item;
	}
}
