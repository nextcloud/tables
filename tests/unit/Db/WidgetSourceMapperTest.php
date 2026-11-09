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

	public function testSettingASourceReplacesTheOldOneAndNullRemovesIt(): void {
		[$first, $second, $third] = self::WIDGETS;
		$this->mapper->setSource($first, 990221);
		$this->mapper->setSource($second, 990222);

		$this->mapper->setSource($first, 990223);
		$this->mapper->setSource($second, null);

		$this->assertSame([$first => 990223], $this->mapper->findNodeRelIdsByWidgetIds(self::WIDGETS));
		$this->assertSame([], $this->mapper->findNodeRelIdsByWidgetIds([$third]));
		$this->assertSame([], $this->mapper->findNodeRelIdsByWidgetIds([]));
	}

	public function testDeletingByWidgetKeepsOtherWidgetsSources(): void {
		[$first, $second] = self::WIDGETS;
		$this->mapper->setSource($first, 990231);
		$this->mapper->setSource($second, 990232);

		$this->mapper->deleteByWidgetIds([$first]);

		$this->assertSame([$second => 990232], $this->mapper->findNodeRelIdsByWidgetIds(self::WIDGETS));
	}

	public function testDeletingByNodeRelationRemovesEverySourceShowingIt(): void {
		[$first, $second, $third] = self::WIDGETS;
		$this->mapper->setSource($first, 990241);
		$this->mapper->setSource($second, 990241);
		$this->mapper->setSource($third, 990242);

		$this->mapper->deleteByNodeRelIds([990241]);
		$this->mapper->deleteByNodeRelIds([]);

		$this->assertSame([$third => 990242], $this->mapper->findNodeRelIdsByWidgetIds(self::WIDGETS));
	}
}
