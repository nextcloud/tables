<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\WidgetSourceMapper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;

/**
 * Runs against the database.
 */
class WidgetSourceMapperTest extends DatabaseTestCase {
	private const WIDGETS = [990201, 990202, 990203];

	private WidgetSourceMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new WidgetSourceMapper($this->connectionAdapter);
	}

	protected function tearDown(): void {
		$this->mapper->deleteByWidgetIds(self::WIDGETS);
		parent::tearDown();
	}

	public function testSourcesAreReadByWidgetId(): void {
		[$first, $second, $third] = self::WIDGETS;
		$this->mapper->setSource($first, 11);
		$this->mapper->setSource($second, 12);

		$this->assertSame([$first => 11, $second => 12], $this->sorted($this->mapper->findNodeRelIdsByWidgetIds(self::WIDGETS)));
		$this->assertSame([], $this->mapper->findNodeRelIdsByWidgetIds([$third]));
		$this->assertSame([], $this->mapper->findNodeRelIdsByWidgetIds([]));
	}

	public function testSettingASourceReplacesTheOldOneAndNullRemovesIt(): void {
		[$first, $second] = self::WIDGETS;
		$this->mapper->setSource($first, 21);
		$this->mapper->setSource($second, 22);

		$this->mapper->setSource($first, 23);
		$this->mapper->setSource($second, null);

		$this->assertSame([$first => 23], $this->mapper->findNodeRelIdsByWidgetIds(self::WIDGETS));
	}

	public function testDeletingByWidgetKeepsOtherWidgetsSources(): void {
		[$first, $second] = self::WIDGETS;
		$this->mapper->setSource($first, 31);
		$this->mapper->setSource($second, 32);

		$this->mapper->deleteByWidgetIds([$first]);

		$this->assertSame([$second => 32], $this->mapper->findNodeRelIdsByWidgetIds(self::WIDGETS));
	}

	public function testDeletingByNodeRelationRemovesEverySourceShowingIt(): void {
		[$first, $second, $third] = self::WIDGETS;
		$this->mapper->setSource($first, 41);
		$this->mapper->setSource($second, 41);
		$this->mapper->setSource($third, 42);

		$this->mapper->deleteByNodeRelIds([41]);
		$this->mapper->deleteByNodeRelIds([]);

		$this->assertSame([$third => 42], $this->mapper->findNodeRelIdsByWidgetIds(self::WIDGETS));
	}

	/**
	 * @param array<int, int> $sources
	 * @return array<int, int>
	 */
	private function sorted(array $sources): array {
		ksort($sources);
		return $sources;
	}
}
