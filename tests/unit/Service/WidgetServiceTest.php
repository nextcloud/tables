<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Widget;
use OCA\Tables\Db\WidgetMapper;
use OCA\Tables\Db\WidgetSourceMapper;
use OCA\Tables\Model\WidgetInput;
use OCA\Tables\Service\WidgetService;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Runs against the database: widgets, their settings and sources.
 */
class WidgetServiceTest extends DatabaseTestCase {
	private const CONTEXT = 990001;
	private const OTHER_CONTEXT = 990002;

	private int $page;
	private int $otherPage;
	/** @var array<int, int> node relation ids of the application, by the number a test names them by */
	private array $relations = [];

	private WidgetService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new WidgetService(
			new WidgetMapper($this->connectionAdapter),
			new WidgetSourceMapper($this->connectionAdapter),
			$this->connectionAdapter,
		);
		$this->removeFixtures();
		$this->page = $this->insertRow('tables_contexts_page', ['context_id' => self::CONTEXT, 'page_type' => 'grid', 'technical_name' => 'one']);
		$this->otherPage = $this->insertRow('tables_contexts_page', ['context_id' => self::CONTEXT, 'page_type' => 'grid', 'technical_name' => 'two']);
	}

	protected function tearDown(): void {
		$this->removeFixtures();
		parent::tearDown();
	}

	/**
	 * Removes the pages, widgets and relations of the test applications, found by their
	 * fixed ids, so a run that stopped half way does not break the next one.
	 */
	private function removeFixtures(): void {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->select('id')
			->from('tables_contexts_page')
			->where($qb->expr()->in('context_id', $qb->createNamedParameter([self::CONTEXT, self::OTHER_CONTEXT], IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $qb->executeQuery();
		$pageIds = array_map('intval', $result->fetchFirstColumn());
		$result->closeCursor();
		$this->service->deleteByPageIds($pageIds);
		foreach (['tables_contexts_page', 'tables_contexts_rel_context_node'] as $table) {
			$qb = $this->connectionAdapter->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->in('context_id', $qb->createNamedParameter([self::CONTEXT, self::OTHER_CONTEXT], IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		$this->relations = [];
	}

	/**
	 * @param array{gridX: int, gridY: int, gridWidth: int, gridHeight: int} $position
	 * @param array<string, mixed> $settings
	 */
	private function insert(int $pageId, string $type, ?string $title, array $position, array $settings, ?int $nodeRelId, ?string $icon = null, ?string $description = null): Widget {
		return $this->service->insertAll($pageId, [new WidgetInput(null, $type, $title, $icon, $description, $position['gridX'], $position['gridY'], $position['gridWidth'], $position['gridHeight'], $settings, $nodeRelId)])[0];
	}

	/**
	 * @param list<array{id: ?int, type: string, title: ?string, icon?: ?string, description?: ?string, position: array{gridX: int, gridY: int, gridWidth: int, gridHeight: int}, settings: array<string, mixed>, nodeRelId: ?int}> $rows
	 */
	private function sync(int $pageId, array $rows): void {
		$this->service->syncPage($pageId, array_map(static fn (array $row): WidgetInput => new WidgetInput(
			$row['id'], $row['type'], $row['title'], $row['icon'] ?? null, $row['description'] ?? null,
			$row['position']['gridX'], $row['position']['gridY'], $row['position']['gridWidth'], $row['position']['gridHeight'],
			$row['settings'], $row['nodeRelId'],
		), $rows));
	}

	/**
	 * A node relation of the application of the test pages.
	 */
	private function rel(int $number): int {
		return $this->relations[$number] ??= $this->insertRow('tables_contexts_rel_context_node', ['context_id' => self::CONTEXT, 'node_id' => $number, 'node_type' => 0, 'permissions' => 1]);
	}

	/**
	 * @param array<string, int|string> $values
	 */
	private function insertRow(string $table, array $values): int {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->insert($table)
			->values(array_map(static fn (int|string $value) => $qb->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR), $values))
			->executeStatement();
		return $qb->getLastInsertId();
	}

	public function testASourceOfAnotherApplicationIsRefusedOnInsertAndSync(): void {
		$foreign = $this->insertRow('tables_contexts_rel_context_node', ['context_id' => self::OTHER_CONTEXT, 'node_id' => 1, 'node_type' => 0, 'permissions' => 1]);
		$widget = $this->insert($this->page, 'data', null, $this->position(0, 0, 6, 5), [], $this->rel(1));

		try {
			$this->insert($this->page, 'data', null, $this->position(6, 0, 6, 5), [], $foreign);
			$this->fail('An insert accepted a node of another application.');
		} catch (\InvalidArgumentException) {
		}
		try {
			$this->sync($this->page, [
				['id' => $widget->getId(), 'type' => 'data', 'title' => null, 'position' => $this->position(0, 0, 6, 5), 'settings' => [], 'nodeRelId' => $foreign],
			]);
			$this->fail('A sync accepted a node of another application.');
		} catch (\InvalidArgumentException) {
		}

		$widgets = $this->service->findByPageIds([$this->page])[$this->page];
		$this->assertSame([['nodeRelId' => $this->rel(1)]], array_column($widgets, 'source'));
	}

	public function testInsertedWidgetsAreReadBackWithSettingsAndSource(): void {
		$data = $this->insert($this->page, 'data', 'Tasks', $this->position(0, 4, 12, 5), [], $this->rel(31), 'star', 'Open tasks');
		$header = $this->insert($this->page, 'header', null, $this->position(0, 0, 12, 2), [
			'text' => 'Projects',
			'showTitle' => false,
			'level' => 2,
			'color' => null,
		], null);
		$this->insert($this->otherPage, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'Other'], null);

		$byPage = $this->service->findByPageIds([$this->page]);

		$this->assertSame([$this->page], array_keys($byPage));
		$this->assertSame([
			[
				'id' => $header->getId(),
				'type' => 'header',
				'title' => null,
				'icon' => null,
				'description' => null,
				'gridX' => 0,
				'gridY' => 0,
				'gridWidth' => 12,
				'gridHeight' => 2,
				'settings' => ['color' => null, 'level' => 2, 'showTitle' => false, 'text' => 'Projects'],
				'source' => null,
			],
			[
				'id' => $data->getId(),
				'type' => 'data',
				'title' => 'Tasks',
				'icon' => 'star',
				'description' => 'Open tasks',
				'gridX' => 0,
				'gridY' => 4,
				'gridWidth' => 12,
				'gridHeight' => 5,
				'settings' => [],
				'source' => ['nodeRelId' => $this->rel(31)],
			],
		], json_decode(json_encode($byPage[$this->page]), true));
	}

	public function testDeletingPagesDeletesTheirWidgetsAndSources(): void {
		$widget = $this->insert($this->page, 'data', null, $this->position(0, 0, 6, 5), [], $this->rel(51));
		$other = $this->insert($this->otherPage, 'data', null, $this->position(0, 0, 6, 5), [], $this->rel(52));

		$this->service->deleteByPageIds([$this->page]);

		$this->assertSame([], $this->service->findByPageIds([$this->page]));
		$this->assertSame([], (new WidgetSourceMapper($this->connectionAdapter))->findNodeRelIdsByWidgetIds([$widget->getId()]));
		$this->assertSame([[$other->getId(), ['nodeRelId' => $this->rel(52)]]], array_map(static fn (array $widget): array => [$widget['id'], $widget['source']], $this->service->findByPageIds([$this->otherPage])[$this->otherPage]));
	}

	public function testSyncUpdatesListedInsertsNewAndDeletesMissingWidgets(): void {
		$kept = $this->insert($this->page, 'header', null, $this->position(0, 0, 12, 2), ['heading' => 'Old', 'subheading' => 'Gone'], null);
		$removed = $this->insert($this->page, 'data', null, $this->position(0, 2, 12, 5), [], $this->rel(61));
		$other = $this->insert($this->otherPage, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'Stays'], null);

		$this->sync($this->page, [
			['id' => $kept->getId(), 'type' => 'header', 'title' => 'Intro', 'position' => $this->position(0, 0, 6, 3), 'settings' => ['heading' => 'New'], 'nodeRelId' => null],
			['id' => null, 'type' => 'data', 'title' => null, 'position' => $this->position(6, 0, 6, 5), 'settings' => [], 'nodeRelId' => $this->rel(62)],
		]);

		$widgets = $this->service->findByPageIds([$this->page, $this->otherPage]);
		$this->assertCount(2, $widgets[$this->page]);
		[$header, $data] = $widgets[$this->page];
		$this->assertSame($kept->getId(), $header['id']);
		$this->assertSame(['Intro', 0, 0, 6, 3], [$header['title'], $header['gridX'], $header['gridY'], $header['gridWidth'], $header['gridHeight']]);
		$this->assertSame(['heading' => 'New'], (array)$header['settings']);
		$this->assertNotSame($removed->getId(), $data['id']);
		$this->assertSame(['nodeRelId' => $this->rel(62)], $data['source']);
		$this->assertSame([], (new WidgetSourceMapper($this->connectionAdapter))->findNodeRelIdsByWidgetIds([$removed->getId()]));
		$this->assertSame($other->getId(), $widgets[$this->otherPage][0]['id']);
	}

	public function testSyncWritesAndClearsTheOptionalFields(): void {
		$widget = $this->insert($this->page, 'text', 'Notes', $this->position(0, 0, 6, 2), ['text' => 'A'], null, 'star', 'Before');
		$sync = fn (?string $title, ?string $icon, ?string $description, array $settings) => $this->sync($this->page, [
			['id' => $widget->getId(), 'type' => 'text', 'title' => $title, 'icon' => $icon, 'description' => $description, 'position' => $this->position(0, 0, 6, 2), 'settings' => $settings, 'nodeRelId' => null],
		]);
		$read = fn (): array => array_map(static fn (array $widget): array => [$widget['title'], $widget['icon'], $widget['description'], json_encode($widget['settings'])], $this->service->findByPageIds([$this->page])[$this->page]);

		$sync('Tasks', 'home', 'After', ['text' => 'B']);
		$this->assertSame([['Tasks', 'home', 'After', '{"text":"B"}']], $read());

		$sync(null, null, null, []);
		$this->assertSame([[null, null, null, '{}']], $read());
	}

	public function testAFailedSyncChangesNothing(): void {
		$kept = $this->insert($this->page, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'A'], null);

		try {
			$this->sync($this->page, [
				['id' => null, 'type' => 'text', 'title' => null, 'position' => $this->position(0, 0, 6, 2), 'settings' => ['text' => NAN], 'nodeRelId' => null],
			]);
			$this->fail('Settings that are no JSON were saved.');
		} catch (\JsonException) {
		}

		$this->assertSame([$kept->getId()], array_column($this->service->findByPageIds([$this->page])[$this->page], 'id'));
	}

	public function testSyncRejectsTheIdOfAWidgetOnAnotherPage(): void {
		$own = $this->insert($this->page, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'Own'], null);
		$other = $this->insert($this->otherPage, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'Other'], null);

		try {
			$this->sync($this->page, [
				['id' => $other->getId(), 'type' => 'text', 'title' => null, 'position' => $this->position(0, 0, 6, 2), 'settings' => ['text' => 'Moved'], 'nodeRelId' => null],
			]);
			$this->fail('A widget of another page was accepted.');
		} catch (\InvalidArgumentException) {
		}

		$this->assertSame([$own->getId()], array_column($this->service->findByPageIds([$this->page])[$this->page], 'id'));
		$this->assertSame(['text' => 'Other'], (array)$this->service->findByPageIds([$this->otherPage])[$this->otherPage][0]['settings']);
	}

	/**
	 * @return array{gridX: int, gridY: int, gridWidth: int, gridHeight: int}
	 */
	private function position(int $x, int $y, int $width, int $height): array {
		return ['gridX' => $x, 'gridY' => $y, 'gridWidth' => $width, 'gridHeight' => $height];
	}
}
