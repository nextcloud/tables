<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\WidgetMapper;
use OCA\Tables\Db\WidgetSourceMapper;
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
		);
		$this->page = $this->insertRow('tables_contexts_page', ['context_id' => self::CONTEXT, 'page_type' => 'grid', 'technical_name' => 'one']);
		$this->otherPage = $this->insertRow('tables_contexts_page', ['context_id' => self::CONTEXT, 'page_type' => 'grid', 'technical_name' => 'two']);
	}

	protected function tearDown(): void {
		$this->service->deleteByPageIds([$this->page, $this->otherPage]);
		foreach (['tables_contexts_page', 'tables_contexts_rel_context_node'] as $table) {
			$qb = $this->connectionAdapter->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->in('context_id', $qb->createNamedParameter([self::CONTEXT, self::OTHER_CONTEXT], IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		parent::tearDown();
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
		$widget = $this->service->insert($this->page, 'data', null, $this->position(0, 0, 6, 5), [], $this->rel(1));

		try {
			$this->service->insert($this->page, 'data', null, $this->position(6, 0, 6, 5), [], $foreign);
			$this->fail('An insert accepted a node of another application.');
		} catch (\InvalidArgumentException) {
		}
		try {
			$this->service->syncPage($this->page, [
				['id' => $widget->getId(), 'type' => 'data', 'title' => null, 'position' => $this->position(0, 0, 6, 5), 'settings' => [], 'nodeRelId' => $foreign],
			]);
			$this->fail('A sync accepted a node of another application.');
		} catch (\InvalidArgumentException) {
		}

		$widgets = $this->service->findByPageIds([$this->page])[$this->page];
		$this->assertSame([['nodeRelId' => $this->rel(1)]], array_column($widgets, 'source'));
	}

	public function testInsertedWidgetsAreReadBackWithSettingsAndSource(): void {
		$data = $this->service->insert($this->page, 'data', 'Tasks', $this->position(0, 4, 12, 5), [], $this->rel(31), 'star', 'Open tasks');
		$header = $this->service->insert($this->page, 'header', null, $this->position(0, 0, 12, 2), [
			'text' => 'Projects',
			'showTitle' => false,
			'level' => 2,
			'color' => null,
		], null);
		$this->service->insert($this->otherPage, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'Other'], null);

		$byPage = $this->service->findByPageIds([$this->page]);

		$this->assertSame([$this->page], array_keys($byPage));
		$this->assertSame(json_encode([
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
				'settings' => (object)['text' => 'Projects', 'showTitle' => false, 'level' => 2, 'color' => null],
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
				'settings' => new \stdClass(),
				'source' => ['nodeRelId' => $this->rel(31)],
			],
		]), json_encode($byPage[$this->page]));
	}

	public function testRemovingANodeKeepsTheWidgetWithoutSource(): void {
		$kept = $this->service->insert($this->page, 'data', null, $this->position(0, 0, 6, 5), [], $this->rel(41));
		$unlinked = $this->service->insert($this->page, 'data', null, $this->position(6, 0, 6, 5), ['pageSize' => 10], $this->rel(42));

		$this->service->deleteSourcesByNodeRelIds([$this->rel(42)]);

		$widgets = $this->service->findByPageIds([$this->page])[$this->page];
		$this->assertSame([$kept->getId(), $unlinked->getId()], array_column($widgets, 'id'));
		$this->assertSame(['nodeRelId' => $this->rel(41)], $widgets[0]['source']);
		$this->assertNull($widgets[1]['source']);
		$this->assertSame(['pageSize' => 10], (array)$widgets[1]['settings']);
	}

	public function testDeletingPagesDeletesWidgetsSettingsAndSources(): void {
		$widget = $this->service->insert($this->page, 'data', null, $this->position(0, 0, 6, 5), ['pageSize' => 10], $this->rel(51));
		$other = $this->service->insert($this->otherPage, 'data', null, $this->position(0, 0, 6, 5), ['pageSize' => 20], $this->rel(52));

		$this->service->deleteByPageIds([$this->page]);

		$this->assertSame([], $this->service->findByPageIds([$this->page]));
		$this->assertNull($this->storedSettings($widget->getId()));
		$this->assertSame([], (new WidgetSourceMapper($this->connectionAdapter))->findNodeRelIdsByWidgetIds([$widget->getId()]));
		$this->assertSame(['nodeRelId' => $this->rel(52)], $this->service->findByPageIds([$this->otherPage])[$this->otherPage][0]['source']);
		$this->assertSame(['pageSize' => 20], (array)$this->service->findByPageIds([$this->otherPage])[$this->otherPage][0]['settings']);
		$this->assertSame('{"pageSize":20}', $this->storedSettings($other->getId()));
	}

	public function testSettingsAreStoredAsOneJsonObjectOnTheWidgetRow(): void {
		$header = $this->service->insert($this->page, 'header', null, $this->position(0, 0, 12, 2), ['text' => 'A', 'level' => 2, 'showTitle' => false, 'color' => null], null);
		$data = $this->service->insert($this->page, 'data', null, $this->position(0, 2, 12, 5), [], $this->rel(61));

		$this->assertSame('{"text":"A","level":2,"showTitle":false,"color":null}', $this->storedSettings($header->getId()));
		$this->assertSame('{}', $this->storedSettings($data->getId()));
		$this->assertSame([], (array)$this->service->findByPageIds([$this->page])[$this->page][1]['settings']);
	}

	private function storedSettings(int $widgetId): ?string {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->select('settings')
			->from('tables_contexts_widget')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($widgetId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$value = $result->fetchOne();
		$result->closeCursor();
		return $value === false || $value === null ? null : (string)$value;
	}

	public function testSyncUpdatesListedInsertsNewAndDeletesMissingWidgets(): void {
		$kept = $this->service->insert($this->page, 'header', null, $this->position(0, 0, 12, 2), ['heading' => 'Old', 'subheading' => 'Gone'], null);
		$removed = $this->service->insert($this->page, 'data', null, $this->position(0, 2, 12, 5), [], $this->rel(61));
		$other = $this->service->insert($this->otherPage, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'Stays'], null);

		$this->service->syncPage($this->page, [
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

	public function testSyncWithNoSettingsClearsTheStoredSettings(): void {
		$widget = $this->service->insert($this->page, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'A'], null);

		$this->service->syncPage($this->page, [
			['id' => $widget->getId(), 'type' => 'text', 'title' => null, 'position' => $this->position(0, 0, 6, 2), 'settings' => [], 'nodeRelId' => null],
		]);

		$this->assertSame([], (array)$this->service->findByPageIds([$this->page])[$this->page][0]['settings']);
		$this->assertSame('{}', $this->storedSettings($widget->getId()));
	}

	public function testSyncWithNoWidgetsEmptiesThePage(): void {
		$this->service->insert($this->page, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'A'], null);

		$this->service->syncPage($this->page, []);

		$this->assertSame([], $this->service->findByPageIds([$this->page]));
	}

	public function testSyncClearsATitleBackToNull(): void {
		$widget = $this->service->insert($this->page, 'text', 'Notes', $this->position(0, 0, 6, 2), ['text' => 'A'], null);

		$this->service->syncPage($this->page, [
			['id' => $widget->getId(), 'type' => 'text', 'title' => null, 'position' => $this->position(0, 0, 6, 2), 'settings' => ['text' => 'A'], 'nodeRelId' => null],
		]);

		$this->assertNull($this->service->findByPageIds([$this->page])[$this->page][0]['title']);
	}

	public function testSyncWritesAndClearsTheIconAndDescription(): void {
		$widget = $this->service->insert($this->page, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'A'], null, 'star', 'Before');

		$this->service->syncPage($this->page, [
			['id' => $widget->getId(), 'type' => 'text', 'title' => null, 'icon' => 'home', 'description' => 'After', 'position' => $this->position(0, 0, 6, 2), 'settings' => ['text' => 'A'], 'nodeRelId' => null],
			['id' => null, 'type' => 'text', 'title' => null, 'icon' => 'star', 'description' => 'New', 'position' => $this->position(6, 0, 6, 2), 'settings' => ['text' => 'B'], 'nodeRelId' => null],
		]);
		$widgets = $this->service->findByPageIds([$this->page])[$this->page];
		$this->assertSame([['home', 'After'], ['star', 'New']], array_map(static fn (array $widget): array => [$widget['icon'], $widget['description']], $widgets));

		$this->service->syncPage($this->page, [
			['id' => $widget->getId(), 'type' => 'text', 'title' => null, 'icon' => null, 'description' => null, 'position' => $this->position(0, 0, 6, 2), 'settings' => ['text' => 'A'], 'nodeRelId' => null],
		]);
		$widgets = $this->service->findByPageIds([$this->page])[$this->page];
		$this->assertSame([[null, null]], array_map(static fn (array $widget): array => [$widget['icon'], $widget['description']], $widgets));
	}

	public function testSyncRejectsTheIdOfAWidgetOnAnotherPage(): void {
		$own = $this->service->insert($this->page, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'Own'], null);
		$other = $this->service->insert($this->otherPage, 'text', null, $this->position(0, 0, 6, 2), ['text' => 'Other'], null);

		try {
			$this->service->syncPage($this->page, [
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
