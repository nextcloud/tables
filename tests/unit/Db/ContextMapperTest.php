<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelation;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\MenuItem;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContent;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;

/**
 * Runs against the database: the pages of a context as the mapper reads them.
 */
class ContextMapperTest extends DatabaseTestCase {
	private const OWNER = 'context-mapper-test-owner';

	private ContextMapper $mapper;
	private ContextNodeRelationMapper $nodeRelMapper;
	private PageMapper $pageMapper;
	private MenuItemMapper $menuItemMapper;
	private PageContentMapper $pageContentMapper;
	/** @var Context[] */
	private array $contexts = [];

	protected function setUp(): void {
		parent::setUp();
		$userHelper = $this->createMock(UserHelper::class);
		$userHelper->method('getGroupIdsForUser')->willReturn([]);
		$this->menuItemMapper = new MenuItemMapper($this->connectionAdapter);
		$this->mapper = new ContextMapper($this->connectionAdapter, $userHelper, $this->createMock(GroupHelper::class), $this->menuItemMapper);
		$this->nodeRelMapper = new ContextNodeRelationMapper($this->connectionAdapter);
		$this->pageMapper = new PageMapper($this->connectionAdapter);
		$this->pageContentMapper = new PageContentMapper($this->connectionAdapter);
	}

	protected function tearDown(): void {
		foreach ($this->contexts as $context) {
			foreach ($this->pageMapper->getPageIdsForContext($context->getId()) as $pageId) {
				$this->pageContentMapper->deleteByPageId($pageId);
				$this->pageMapper->deleteByPageId($pageId);
			}
			$this->nodeRelMapper->deleteAllByContextId($context->getId());
			$this->menuItemMapper->deleteAllByContextId($context->getId());
			$this->mapper->delete($context);
		}
		parent::tearDown();
	}

	public function testGridPageHasItsFieldsAndNoStartPageContent(): void {
		$context = $this->createContext();
		$first = $this->addNode($context, 11);
		$second = $this->addNode($context, 12);
		$startPage = $this->addPage($context, Page::TYPE_STARTPAGE, 'dashboard', null, null, null);
		$firstContent = $this->addContent($startPage, $first, 10);
		$secondContent = $this->addContent($startPage, $second, 20);
		$gridPage = $this->addPage($context, Page::TYPE_GRID, 'overview', 'Overview', 'star', 'Open work at a glance');

		$pages = $this->mapper->findById($context->getId(), self::OWNER)->getPages();

		$this->assertEqualsCanonicalizing([$startPage->getId(), $gridPage->getId()], array_keys($pages));
		$this->assertSame([
			'content' => [],
			'id' => $gridPage->getId(),
			'page_type' => Page::TYPE_GRID,
			'technical_name' => 'overview',
			'title' => 'Overview',
			'icon' => 'star',
			'description' => 'Open work at a glance',
		], $pages[$gridPage->getId()]);
		$this->assertNull($pages[$startPage->getId()]['title']);
		$this->assertSame('dashboard', $pages[$startPage->getId()]['technical_name']);
		$this->assertSame([$firstContent->getId(), $secondContent->getId()], array_keys($pages[$startPage->getId()]['content']));
		$this->assertSame([$first->getId(), $second->getId()], array_keys($this->mapper->findById($context->getId(), self::OWNER)->getNodes()));
	}

	public function testTheApplicationsContainingANodeAreFoundWithoutTheirMenus(): void {
		$context = $this->createContext();
		$this->addNode($context, 11);
		$other = $this->createContext();
		$this->addNode($other, 12);
		$menuItem = new MenuItem();
		$menuItem->setContextId($context->getId());
		$menuItem->setLabel('Home');
		$menuItem->setTargetType(MenuItem::TARGET_URL);
		$menuItem->setUrl('https://example.com');
		$menuItem->setTechnicalName('home');
		$this->menuItemMapper->insert($menuItem);

		$found = $this->mapper->findAllContainingNode(Application::NODE_TYPE_TABLE, 11, self::OWNER);

		$this->assertSame([$context->getId()], array_map(static fn (Context $context): int => $context->getId(), $found));
		$this->assertSame([11], array_column($found[0]->getNodes(), 'node_id'));
		$this->assertNull($found[0]->getMenuItems());
		$this->assertArrayNotHasKey('menuItems', $found[0]->jsonSerialize());
		$this->assertSame(['home'], array_column($this->mapper->findById($context->getId(), self::OWNER)->getMenuItems(), 'technicalName'));
	}

	private function createContext(): Context {
		$context = new Context();
		$context->setName('Mapper test');
		$context->setIcon('tables');
		$context->setDescription('');
		$context->setOwnerId(self::OWNER);
		$context->setOwnerType(Application::OWNER_TYPE_USER);
		$this->contexts[] = $context = $this->mapper->insert($context);
		return $context;
	}

	private function addNode(Context $context, int $nodeId): ContextNodeRelation {
		$relation = new ContextNodeRelation();
		$relation->setContextId($context->getId());
		$relation->setNodeId($nodeId);
		$relation->setNodeType(Application::NODE_TYPE_TABLE);
		$relation->setPermissions(Application::PERMISSION_READ);
		return $this->nodeRelMapper->insert($relation);
	}

	private function addPage(Context $context, string $type, string $technicalName, ?string $title, ?string $icon, ?string $description): Page {
		$page = new Page();
		$page->setContextId($context->getId());
		$page->setPageType($type);
		$page->setTechnicalName($technicalName);
		$page->setTitle($title);
		$page->setIcon($icon);
		$page->setDescription($description);
		return $this->pageMapper->insert($page);
	}

	private function addContent(Page $page, ContextNodeRelation $relation, int $order): PageContent {
		$content = new PageContent();
		$content->setPageId($page->getId());
		$content->setNodeRelId($relation->getId());
		$content->setOrder($order);
		return $this->pageContentMapper->insert($content);
	}
}
