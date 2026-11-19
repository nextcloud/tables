<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\MenuItem;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;

/**
 * Runs against the database.
 */
class MenuItemMapperTest extends DatabaseTestCase {
	private const CONTEXT = 990601;
	private const OTHER_CONTEXT = 990602;

	private MenuItemMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new MenuItemMapper($this->connectionAdapter);
	}

	protected function tearDown(): void {
		$this->mapper->deleteByContextId(self::CONTEXT);
		$this->mapper->deleteByContextId(self::OTHER_CONTEXT);
		parent::tearDown();
	}

	public function testClearingAGroupLeavesTheItemsOfOtherApplications(): void {
		$group = $this->insert(self::CONTEXT, null, 0);
		$inGroup = $this->insert(self::CONTEXT, $group->getId(), 1);
		$elsewhere = $this->insert(self::OTHER_CONTEXT, $group->getId(), 0);

		$this->mapper->clearParent(self::CONTEXT, $group->getId());

		$this->assertNull($this->mapper->findById($inGroup->getId())->getParentId());
		$this->assertSame($group->getId(), $this->mapper->findById($elsewhere->getId())->getParentId());
	}

	private function insert(int $contextId, ?int $parentId, int $order): MenuItem {
		$item = new MenuItem();
		$item->setContextId($contextId);
		$item->setParentId($parentId);
		$item->setOrder($order);
		$item->setTitle('Item ' . $order);
		return $this->mapper->insert($item);
	}
}
