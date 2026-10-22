<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Widget;
use OCA\Tables\Db\WidgetMapper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;

/**
 * Runs against the database.
 */
class WidgetMapperTest extends DatabaseTestCase {
	private const PAGE = 990101;
	private const OTHER_PAGE = 990102;

	private WidgetMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new WidgetMapper($this->connectionAdapter);
	}

	protected function tearDown(): void {
		$this->mapper->deleteByIds(array_merge(
			$this->mapper->findIdsByPageId(self::PAGE),
			$this->mapper->findIdsByPageId(self::OTHER_PAGE),
		));
		parent::tearDown();
	}

	public function testAWidgetIsStoredWithAllItsFields(): void {
		$widget = $this->widget(self::PAGE, 'header', 'Welcome', 2, 3, 8, 4, ['heading' => 'Projects', 'showTitle' => false]);
		$widget->setIcon('star');
		$widget->setDescription('What the team works on');
		$inserted = $this->mapper->insert($widget);

		$found = $this->mapper->findByPageIds([self::PAGE]);

		$this->assertCount(1, $found);
		$serialized = $found[0]->jsonSerialize();
		$serialized['settings'] = json_encode($serialized['settings']);
		$this->assertSame([
			'id' => $inserted->getId(),
			'pageId' => self::PAGE,
			'type' => 'header',
			'title' => 'Welcome',
			'icon' => 'star',
			'description' => 'What the team works on',
			'gridX' => 2,
			'gridY' => 3,
			'gridWidth' => 8,
			'gridHeight' => 4,
			'settings' => '{"heading":"Projects","showTitle":false}',
		], $serialized);
	}

	public function testWidgetsAreFoundByPageInGridOrder(): void {
		$bottom = $this->insert(self::PAGE, 'data', null, 0, 5);
		$topRight = $this->insert(self::PAGE, 'text', null, 6, 0);
		$topLeft = $this->insert(self::PAGE, 'text', null, 0, 0);
		$other = $this->insert(self::OTHER_PAGE, 'text', null, 0, 0);

		$ids = static fn (array $widgets): array => array_map(static fn (Widget $widget): int => $widget->getId(), $widgets);

		$this->assertSame([$topLeft->getId(), $topRight->getId(), $bottom->getId()], $ids($this->mapper->findByPageIds([self::PAGE])));
		$this->assertSame(
			[$topLeft->getId(), $topRight->getId(), $bottom->getId(), $other->getId()],
			$ids($this->mapper->findByPageIds([self::OTHER_PAGE, self::PAGE])),
		);
		$this->assertSame([], $this->mapper->findByPageIds([]));
	}

	public function testIdsOfAPageAreListed(): void {
		$first = $this->insert(self::PAGE, 'text', null, 0, 0);
		$second = $this->insert(self::PAGE, 'text', null, 0, 1);
		$this->insert(self::OTHER_PAGE, 'text', null, 0, 0);

		$ids = $this->mapper->findIdsByPageId(self::PAGE);
		sort($ids);

		$this->assertSame([$first->getId(), $second->getId()], $ids);
	}

	public function testOnlyTheGivenWidgetsAreDeleted(): void {
		$deleted = $this->insert(self::PAGE, 'text', null, 0, 0);
		$kept = $this->insert(self::PAGE, 'text', null, 0, 1);

		$this->mapper->deleteByIds([$deleted->getId()]);
		$this->mapper->deleteByIds([]);

		$this->assertSame([$kept->getId()], $this->mapper->findIdsByPageId(self::PAGE));
	}

	/**
	 * @param array<string, string|int|float|bool|null> $settings
	 */
	private function insert(int $pageId, string $type, ?string $title, int $x, int $y, int $width = 6, int $height = 2, array $settings = []): Widget {
		return $this->mapper->insert($this->widget($pageId, $type, $title, $x, $y, $width, $height, $settings));
	}

	/**
	 * @param array<string, string|int|float|bool|null> $settings
	 */
	private function widget(int $pageId, string $type, ?string $title, int $x, int $y, int $width = 6, int $height = 2, array $settings = []): Widget {
		$widget = new Widget();
		$widget->setPageId($pageId);
		$widget->setType($type);
		$widget->setTitle($title);
		$widget->setGridX($x);
		$widget->setGridY($y);
		$widget->setGridWidth($width);
		$widget->setGridHeight($height);
		$widget->setSettingsArray($settings);
		return $widget;
	}
}
