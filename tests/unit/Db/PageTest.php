<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Runs against the database.
 */
class PageTest extends DatabaseTestCase {
	private const CONTEXT = 990301;
	private const OTHER_CONTEXT = 990302;

	private PageMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new PageMapper($this->connectionAdapter);
	}

	protected function tearDown(): void {
		foreach ([self::CONTEXT, self::OTHER_CONTEXT] as $contextId) {
			foreach ($this->mapper->getPageIdsForContext($contextId) as $pageId) {
				$this->mapper->deleteByPageId($pageId);
			}
		}
		parent::tearDown();
	}

	public function testAGridPageKeepsItsTitleIconAndDescription(): void {
		$page = new Page();
		$page->setContextId(self::CONTEXT);
		$page->setPageType(Page::TYPE_GRID);
		$page->setTechnicalName('overview');
		$page->setTitle('Overview');
		$page->setIcon('star');
		$page->setDescription('Open tasks by team');
		$page = $this->mapper->insert($page);

		$this->assertSame([
			'id' => $page->getId(),
			'contextId' => self::CONTEXT,
			'pageType' => 'grid',
			'technicalName' => 'overview',
			'title' => 'Overview',
			'icon' => 'star',
			'description' => 'Open tasks by team',
		], $this->load($page->getId())->jsonSerialize());
	}

	public function testAPageWithoutAGivenNameIsNamedByItsId(): void {
		$page = new Page();
		$page->setContextId(self::CONTEXT);
		$page->setPageType(Page::TYPE_GRID);
		$page->setTitle('Overview');
		$page = $this->mapper->insertWithDefaultName($page);

		$this->assertSame('page_' . $page->getId(), $page->getTechnicalName());
		$this->assertSame('page_' . $page->getId(), $this->load($page->getId())->getTechnicalName());
	}

	public function testOnlyTheAutomaticFormIsADefaultName(): void {
		foreach (['page_7' => true, 'page_123456' => true, 'page' => false, 'page_' => false, 'page_7a' => false, 'my_page_7' => false, 'dashboard' => false] as $name => $isDefault) {
			$this->assertSame($isDefault, Page::isDefaultName($name), $name);
		}
	}

	public function testATechnicalNameIsUniqueWithinAnApplication(): void {
		$this->insertNamed(self::CONTEXT, 'sales');
		$this->insertNamed(self::OTHER_CONTEXT, 'sales');

		try {
			$this->insertNamed(self::CONTEXT, 'sales');
			$this->fail('The unique index accepted a second page named sales');
		} catch (Exception $e) {
			$this->assertSame(Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION, $e->getReason());
		}
		$this->assertCount(1, $this->mapper->getPageIdsForContext(self::CONTEXT));
	}

	private function insertNamed(int $contextId, string $technicalName): Page {
		$page = new Page();
		$page->setContextId($contextId);
		$page->setPageType(Page::TYPE_GRID);
		$page->setTechnicalName($technicalName);
		return $this->mapper->insert($page);
	}

	private function load(int $pageId): Page {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->select('*')
			->from('tables_contexts_page')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();
		$this->assertIsArray($row);
		return Page::fromRow($row);
	}
}
