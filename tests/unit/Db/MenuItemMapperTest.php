<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\MenuItem;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;

/**
 * Covers the SQL that loads menus: the broken flag, the order and the grouping per application.
 */
class MenuItemMapperTest extends DatabaseTestCase {
	private MenuItemMapper $menuItemMapper;
	private ContextMapper $contextMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->cleanupTablesData();
		$this->menuItemMapper = new MenuItemMapper($this->connectionAdapter);
		$this->contextMapper = new ContextMapper(
			$this->connectionAdapter,
			$this->createMock(UserHelper::class),
			$this->createMock(GroupHelper::class),
			$this->menuItemMapper,
		);
	}

	public function testItemsWhoseViewOrTableIsGoneAreBroken(): void {
		$contextId = $this->createContext('Intake');
		$tableId = $this->createTestTable()['id'];
		$viewId = $this->createView($tableId);
		$this->createMenuItem($contextId, 'live_view', MenuItem::TARGET_VIEW, $viewId);
		$this->createMenuItem($contextId, 'gone_view', MenuItem::TARGET_VIEW, $viewId + 100);
		$this->createMenuItem($contextId, 'live_table', MenuItem::TARGET_TABLE, $tableId);
		$this->createMenuItem($contextId, 'gone_table', MenuItem::TARGET_TABLE, $tableId + 100);
		// A link points at neither, so its target id never makes it broken
		$this->createMenuItem($contextId, 'link', MenuItem::TARGET_URL, $viewId + 100);

		$broken = [];
		foreach ($this->menuItemMapper->findByContextId($contextId) as $menuItem) {
			$broken[$menuItem->getTechnicalName()] = $menuItem->isBroken();
		}

		$this->assertSame([
			'live_view' => false,
			'gone_view' => true,
			'live_table' => false,
			'gone_table' => true,
			'link' => false,
		], $broken);
	}

	public function testItemsAreOrderedAndGroupedPerApplication(): void {
		$intakeId = $this->createContext('Intake');
		$reportsId = $this->createContext('Reports');
		$this->createMenuItem($intakeId, 'second', MenuItem::TARGET_URL, null, 20);
		$this->createMenuItem($reportsId, 'overview', MenuItem::TARGET_URL, null, 0);
		$this->createMenuItem($intakeId, 'first', MenuItem::TARGET_URL, null, 10);

		$menus = $this->menuItemMapper->findByContextIds([$intakeId, $reportsId]);

		$this->assertSame([
			$intakeId => ['first', 'second'],
			$reportsId => ['overview'],
		], array_map(self::technicalNames(...), $menus));
		$this->assertSame([], $this->menuItemMapper->findByContextIds([]));
	}

	public function testFindByIdIncludesTheMenu(): void {
		$contextId = $this->createContext('Intake');
		$this->createMenuItem($contextId, 'home', MenuItem::TARGET_URL, null);

		$menuItems = $this->contextMapper->findById($contextId)->getMenuItems();

		$this->assertSame(['home'], array_column($menuItems, 'technicalName'));
	}

	public function testFindAllIncludesTheMenus(): void {
		$intakeId = $this->createContext('Intake');
		$reportsId = $this->createContext('Reports');
		$this->createMenuItem($intakeId, 'home', MenuItem::TARGET_URL, null);
		$this->createMenuItem($reportsId, 'overview', MenuItem::TARGET_URL, null);

		$menus = [];
		foreach ($this->contextMapper->findAll() as $context) {
			$menus[$context->getId()] = array_column($context->getMenuItems(), 'technicalName');
		}
		ksort($menus);

		$this->assertSame([$intakeId => ['home'], $reportsId => ['overview']], $menus);
	}

	/**
	 * @param list<MenuItem> $menuItems
	 * @return list<string>
	 */
	private static function technicalNames(array $menuItems): array {
		return array_map(static fn (MenuItem $menuItem): string => $menuItem->getTechnicalName(), $menuItems);
	}

	private function createContext(string $name): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_contexts_context')
			->values([
				'name' => $qb->createNamedParameter($name),
				'icon' => $qb->createNamedParameter('star'),
				'description' => $qb->createNamedParameter(''),
				'owner_id' => $qb->createNamedParameter('user1'),
				'owner_type' => $qb->createNamedParameter(0),
			])
			->executeStatement();
		return (int)$this->connection->lastInsertId();
	}

	private function createView(int $tableId): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_views')
			->values([
				'table_id' => $qb->createNamedParameter($tableId),
				'title' => $qb->createNamedParameter('Open requests'),
				'technical_name' => $qb->createNamedParameter('open_requests'),
				'description' => $qb->createNamedParameter(''),
				'created_by' => $qb->createNamedParameter('user1'),
				'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
				'last_edit_by' => $qb->createNamedParameter('user1'),
				'last_edit_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
			])
			->executeStatement();
		return (int)$this->connection->lastInsertId();
	}

	private function createMenuItem(int $contextId, string $technicalName, string $targetType, ?int $targetId, int $order = 0): void {
		$menuItem = new MenuItem();
		$menuItem->setContextId($contextId);
		$menuItem->setLabel($technicalName);
		$menuItem->setTargetType($targetType);
		$menuItem->setTargetId($targetId);
		$menuItem->setTechnicalName($technicalName);
		$menuItem->setOrder($order);
		$this->menuItemMapper->insert($menuItem);
	}
}
