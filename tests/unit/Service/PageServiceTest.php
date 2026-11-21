<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\ConflictError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Model\WidgetInput;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\PageService;
use OCA\Tables\Service\WidgetService;
use OCA\Tables\Tests\Unit\Database\DbErrorStub;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PageServiceTest extends TestCase {
	private const START_PAGE = 1;
	private const GRID_PAGE = 2;
	private const NODE_REL = 20;

	private PageService $service;
	/** @var array<int, Page> stored pages by id */
	private array $pages = [];
	private PageMapper&MockObject $pageMapper;
	private PageContentMapper&MockObject $pageContentMapper;
	private WidgetService&MockObject $widgetService;

	protected function setUp(): void {
		parent::setUp();
		$this->pages = [
			self::START_PAGE => $this->page(self::START_PAGE, Page::TYPE_STARTPAGE, 'dashboard', null),
			self::GRID_PAGE => $this->page(self::GRID_PAGE, Page::TYPE_GRID, 'overview', 'Overview'),
		];
		$context = new Context();
		$context->id = 7;
		$context->setNodes([self::NODE_REL => ['id' => self::NODE_REL, 'node_id' => 3, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1]]);
		$contextMapper = $this->createMock(ContextMapper::class);
		$contextMapper->method('findById')->willReturn($context);
		$contextMapper->method('lock')->willReturn(true);

		$this->pageMapper = $this->createMock(PageMapper::class);
		$this->pageMapper->method('findById')->willReturnCallback(function (int $pageId): Page {
			if (!isset($this->pages[$pageId])) {
				throw new DoesNotExistException('Page does not exist');
			}
			return clone $this->pages[$pageId];
		});
		$this->pageContentMapper = $this->createMock(PageContentMapper::class);
		$this->widgetService = $this->createMock(WidgetService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->service = new PageService(
			$contextMapper,
			$this->pageMapper,
			$this->pageContentMapper,
			$this->widgetService,
			new GridWidgetTypes($l10n),
			$this->createMock(IDBConnection::class),
		);
	}

	private function page(int $id, string $type, string $slug, ?string $title): Page {
		$page = new Page();
		$page->id = $id;
		$page->setContextId(7);
		$page->setPageType($type);
		$page->setSlug($slug);
		$page->setTitle($title);
		$page->setVersion(4);
		$page->resetUpdatedFields();
		return $page;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function widget(array $fields = []): array {
		return $fields + ['type' => 'text', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => ['text' => 'Hi']];
	}

	public function testFindReturnsThePageWithItsWidgetsAndVersion(): void {
		$widget = ['id' => 31, 'type' => 'text'];
		$this->widgetService->method('findByPageIds')->with([self::GRID_PAGE])->willReturn([self::GRID_PAGE => [$widget]]);

		$page = $this->service->find(7, self::GRID_PAGE);

		$this->assertSame([self::GRID_PAGE, 7, Page::TYPE_GRID, 'overview', 'Overview', 4, [$widget]], [
			$page['id'], $page['contextId'], $page['pageType'], $page['slug'], $page['title'], $page['version'], $page['widgets'],
		]);
	}

	public function testFindBySlugAsksTheMapperAndAnUnknownNameIsNotFound(): void {
		$this->pageMapper->method('findBySlug')->willReturnCallback(fn (int $contextId, string $name): Page => $name === 'overview' ? $this->pages[self::GRID_PAGE] : throw new DoesNotExistException('none'));
		$this->widgetService->method('findByPageIds')->willReturn([]);

		$this->assertSame(self::GRID_PAGE, $this->service->findBySlug(7, 'overview')['id']);
		$this->expectException(NotFoundError::class);
		$this->service->findBySlug(7, 'missing');
	}

	public function testAPageOfAnotherApplicationIsNotFound(): void {
		$this->pageMapper->expects($this->never())->method('raiseVersion');
		foreach ([
			'find' => fn () => $this->service->find(8, self::GRID_PAGE),
			'update' => fn () => $this->service->update(8, self::GRID_PAGE, 'Mine', null, null, null, null, null),
			'delete' => fn () => $this->service->delete(8, self::GRID_PAGE),
			'unknown page' => fn () => $this->service->find(7, 99),
		] as $case => $call) {
			try {
				$call();
				$this->fail($case . ' was found.');
			} catch (NotFoundError) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testCreateInsertsAGridPageNamedByItsId(): void {
		$this->pageMapper->expects($this->once())->method('insertWithDefaultName')->willReturnCallback(static function (Page $page): Page {
			$page->id = 9;
			$page->setSlug('page_9');
			return $page;
		});

		$page = $this->service->createGridPage(7, ' Planning ', null, 'star', ' Next steps ');

		$this->assertSame([9, Page::TYPE_GRID, 'page_9', 'Planning', 'star', 'Next steps', []], [
			$page['id'], $page['pageType'], $page['slug'], $page['title'], $page['icon'], $page['description'], $page['widgets'],
		]);
	}

	public function testCreateKeepsAGivenSlugAndReportsATakenOne(): void {
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page): Page {
			if ($page->getSlug() === 'taken') {
				throw DbErrorStub::uniqueViolation();
			}
			$page->id = 9;
			return $page;
		});

		$this->assertSame('planning', $this->service->createGridPage(7, 'Planning', 'planning', null, null)['slug']);
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('The slug taken is already used');
		$this->service->createGridPage(7, 'Planning', 'taken', null, null);
	}

	public function testCreateInAnApplicationThatIsGoneIsNotFound(): void {
		$contextMapper = $this->createMock(ContextMapper::class);
		$contextMapper->method('lock')->willReturn(false);
		$this->pageMapper = $this->createMock(PageMapper::class);
		$this->pageMapper->expects($this->never())->method('insertWithDefaultName');
		$service = new PageService($contextMapper, $this->pageMapper, $this->pageContentMapper, $this->widgetService, new GridWidgetTypes($this->createMock(IL10N::class)), $this->createMock(IDBConnection::class));

		$this->expectException(NotFoundError::class);
		$service->createGridPage(7, 'Planning', null, null, null);
	}

	public static function writesOfAPage(): array {
		return [
			'update of the fields' => [static fn (PageService $service): array => $service->update(7, self::GRID_PAGE, 'Planning', null, null, null, null, null)],
			'update of the widgets' => [static fn (PageService $service): array => $service->update(7, self::GRID_PAGE, null, null, null, null, [self::widget()], 4)],
			'delete' => [static fn (PageService $service): array => $service->delete(7, self::GRID_PAGE)],
		];
	}

	/**
	 * @dataProvider writesOfAPage
	 */
	public function testAWriteToAPageOfAnApplicationThatIsGoneStopsAtTheApplicationLock(\Closure $write): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([]);
		$contextMapper = $this->createMock(ContextMapper::class);
		$contextMapper->method('findById')->willReturn($context);
		$contextMapper->expects($this->once())->method('lock')->with(7)->willReturn(false);
		$this->pageMapper->expects($this->never())->method('raiseVersion');
		$this->pageMapper->expects($this->never())->method('update');
		$this->pageMapper->expects($this->never())->method('deleteByPageId');
		$this->widgetService->expects($this->never())->method($this->anything());
		$service = new PageService($contextMapper, $this->pageMapper, $this->pageContentMapper, $this->widgetService, new GridWidgetTypes($this->createMock(IL10N::class)), $this->createMock(IDBConnection::class));

		$this->expectException(NotFoundError::class);
		$this->expectExceptionMessage('Application 7 does not exist');
		$write($service);
	}

	public static function blankTitles(): array {
		return [
			'empty' => [''],
			'spaces' => ['   '],
			'no-break and zero width spaces' => ["\u{00A0}\u{200B}"],
			'too long' => [str_repeat('a', 201)],
		];
	}

	/**
	 * @dataProvider blankTitles
	 */
	public function testAGridPageNeedsATitle(string $title): void {
		$this->pageMapper->expects($this->never())->method('raiseVersion');
		try {
			$this->service->createGridPage(7, $title, null, null, null);
			$this->fail('A page was created without a title.');
		} catch (BadRequestError $e) {
			$this->assertStringContainsString('A page title needs 1 to 200 characters', $e->getMessage());
		}
		$this->expectException(BadRequestError::class);
		$this->service->update(7, self::GRID_PAGE, $title, null, null, null, null, null);
	}

	public function testTheStartPageCanLoseItsTitleAgain(): void {
		$this->pageMapper->method('raiseVersion')->willReturn(true);
		$this->widgetService->method('findByPageIds')->willReturn([]);
		$saved = [];
		$this->pageMapper->method('update')->willReturnCallback(static function (Page $page) use (&$saved): Page {
			$saved[] = $page->getTitle();
			return $page;
		});

		$this->service->update(7, self::START_PAGE, 'Start', null, null, null, null, null);
		$page = $this->service->update(7, self::START_PAGE, '', null, null, null, null, null);

		$this->assertSame(['Start', null], $saved);
		$this->assertNull($page['title']);
	}

	public function testUpdateSyncsWidgetsAndAnswersWithWhatWasStored(): void {
		$this->pageMapper->expects($this->once())->method('raiseVersion')->with(self::GRID_PAGE, 4)->willReturn(true);
		$this->pageMapper->method('findVersion')->willReturn(5);
		$stored = [['id' => 31, 'type' => 'text'], ['id' => 32, 'type' => 'data']];
		$synced = null;
		$this->widgetService->expects($this->once())->method('syncPage')->willReturnCallback(function (int $pageId, array $widgets) use (&$synced, $stored): array {
			$synced = $widgets;
			return $stored;
		});
		$this->widgetService->expects($this->never())->method('findByPageIds');

		$page = $this->service->update(7, self::GRID_PAGE, null, null, null, null, [
			self::widget(['id' => 31]),
			self::widget(['type' => 'data', 'settings' => [], 'source' => ['nodeRelId' => self::NODE_REL]]),
		], 4);

		$this->assertSame([[31, 'text', null], [null, 'data', self::NODE_REL]], array_map(static fn (WidgetInput $w): array => [$w->id, $w->type, $w->nodeRelId], $synced));
		$this->assertSame([$stored, 5], [$page['widgets'], $page['version']]);
	}

	public function testTheStartPageHoldsWidgets(): void {
		$this->pageMapper->method('raiseVersion')->willReturn(true);
		$this->widgetService->expects($this->once())->method('syncPage')->with(self::START_PAGE)->willReturn([]);

		$this->service->update(7, self::START_PAGE, null, null, null, null, [self::widget()], 4);
	}

	public function testASaveOfWidgetsNamesTheVersionItIsBasedOn(): void {
		$this->widgetService->expects($this->never())->method('syncPage');
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('names the version of the page');
		$this->service->update(7, self::GRID_PAGE, null, null, null, null, [self::widget()], null);
	}

	public function testASaveBasedOnAnOlderVersionIsAConflict(): void {
		$this->pageMapper->method('raiseVersion')->willReturn(false);
		$this->widgetService->expects($this->never())->method('syncPage');

		$this->expectException(ConflictError::class);
		$this->service->update(7, self::GRID_PAGE, null, null, null, null, [self::widget()], 3);
	}

	public function testASaveOfAPageDeletedInTheMeantimeIsNotFound(): void {
		$this->pageMapper->method('raiseVersion')->willReturnCallback(function (): bool {
			unset($this->pages[self::GRID_PAGE]);
			return false;
		});
		$this->widgetService->expects($this->never())->method('syncPage');

		$this->expectException(NotFoundError::class);
		$this->service->update(7, self::GRID_PAGE, null, null, null, null, [self::widget()], 4);
	}

	public function testAWidgetThatIsNotOnThePageIsRefusedByName(): void {
		$this->pageMapper->method('raiseVersion')->willReturn(true);
		$this->widgetService->method('syncPage')->willThrowException(new \InvalidArgumentException('Widget 31 is not on page 2'));

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Widget 31 is not on page 2');
		$this->service->update(7, self::GRID_PAGE, null, null, null, null, [self::widget(['id' => 31])], 4);
	}

	public function testASaveAnswersWithTheVersionOfTheLockedRow(): void {
		$this->pageMapper->expects($this->once())->method('raiseVersion')->with(self::GRID_PAGE, null)->willReturn(true);
		$this->pageMapper->method('findVersion')->with(self::GRID_PAGE)->willReturn(9);
		$this->widgetService->method('findByPageIds')->willReturn([]);

		$this->assertSame(9, $this->service->update(7, self::GRID_PAGE, 'Planning', null, null, null, null, null)['version']);
	}

	public function testUpdateChangesThePageFields(): void {
		$this->pageMapper->method('raiseVersion')->willReturn(true);
		$this->widgetService->method('findByPageIds')->willReturn([]);
		$updated = null;
		$this->pageMapper->expects($this->once())->method('update')->willReturnCallback(static function (Page $page) use (&$updated): Page {
			$updated = [$page->getTitle(), $page->getSlug(), $page->getIcon(), $page->getDescription()];
			return $page;
		});

		$this->service->update(7, self::GRID_PAGE, 'Planning', 'overview', 'star', 'Next steps', null, null);

		$this->assertSame(['Planning', 'overview', 'star', 'Next steps'], $updated);
	}

	public function testTheSlugOfAPageCannotChange(): void {
		$this->pageMapper->expects($this->never())->method('raiseVersion');
		$this->pageMapper->expects($this->never())->method('update');

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('The slug of a page cannot be changed.');
		$this->service->update(7, self::GRID_PAGE, 'Planning', 'planning', null, null, null, null);
	}

	public function testAUniqueViolationWithoutARenameIsNotTakenForOne(): void {
		$this->pageMapper->method('raiseVersion')->willReturn(true);
		$this->widgetService->method('syncPage')->willThrowException(DbErrorStub::uniqueViolation());

		$this->expectException(DbErrorStub::class);
		$this->service->update(7, self::GRID_PAGE, null, null, null, null, [self::widget()], 4);
	}

	public static function refusedSlugs(): array {
		return [
			'upper case' => ['Planning', 'A slug has 1 to 200 lower case letters'],
			'reserved for automatic names' => ['page_5', 'The slug page_5 is reserved'],
		];
	}

	/**
	 * @dataProvider refusedSlugs
	 */
	public function testAnInvalidSlugIsRefused(string $slug, string $message): void {
		$this->pageMapper->expects($this->never())->method('insert');

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		$this->service->createGridPage(7, 'Planning', $slug, null, null);
	}

	public function testAPageKeepsItsOwnAutomaticName(): void {
		$this->pages[self::GRID_PAGE]->setSlug('page_2');
		$this->pageMapper->method('raiseVersion')->willReturn(true);
		$this->widgetService->method('findByPageIds')->willReturn([]);

		$this->assertSame('page_2', $this->service->update(7, self::GRID_PAGE, null, 'page_2', null, null, null, null)['slug']);
	}

	public function testAnUnknownIconIsRefused(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('unknown icon');
		$this->service->update(7, self::GRID_PAGE, null, null, 'no-such-icon', null, null, null);
	}

	public static function invalidWidgets(): array {
		return [
			'not an object' => [['x'], 'Widget 0 must be an object'],
			'unknown type' => [[self::widget(['type' => 'chart'])], 'Unknown widget type'],
			'source of another application' => [[self::widget(['type' => 'data', 'settings' => [], 'source' => ['nodeRelId' => 99]])], 'The source of widget 0'],
			'the same id twice' => [[self::widget(['id' => 31]), self::widget(['id' => 31])], 'Widget 1 has the id of another widget'],
			'too many' => [array_fill(0, 101, self::widget()), 'A page holds at most 100 widgets'],
		];
	}

	/**
	 * @dataProvider invalidWidgets
	 */
	public function testInvalidWidgetsAreRefusedBeforeAnyWrite(array $widgets, string $message): void {
		$this->pageMapper->expects($this->never())->method('raiseVersion');
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		$this->service->update(7, self::GRID_PAGE, null, null, null, null, $widgets, 4);
	}

	public function testDeleteRemovesAGridPageWithItsWidgets(): void {
		$this->pageMapper->expects($this->once())->method('raiseVersion')->with(self::GRID_PAGE, null)->willReturn(true);
		$this->widgetService->method('findByPageIds')->willReturn([self::GRID_PAGE => [['id' => 31]]]);
		$this->widgetService->expects($this->once())->method('deleteByPageIds')->with([self::GRID_PAGE]);
		$this->pageMapper->expects($this->once())->method('deleteByPageId')->with(self::GRID_PAGE);

		$this->assertSame([['id' => 31]], $this->service->delete(7, self::GRID_PAGE)['widgets']);
	}

	public function testTheStartPageCannotBeDeleted(): void {
		$this->pageMapper->expects($this->never())->method('deleteByPageId');
		$this->expectException(BadRequestError::class);
		$this->service->delete(7, self::START_PAGE);
	}

	public function testADeleteOfAPageDeletedInTheMeantimeIsNotFound(): void {
		$this->pageMapper->method('raiseVersion')->willReturn(false);
		$this->pageMapper->expects($this->never())->method('deleteByPageId');
		$this->expectException(NotFoundError::class);
		$this->service->delete(7, self::GRID_PAGE);
	}
}
