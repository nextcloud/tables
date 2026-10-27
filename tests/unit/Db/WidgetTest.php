<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Widget;
use OCA\Tables\Db\WidgetSource;
use PHPUnit\Framework\TestCase;

class WidgetTest extends TestCase {
	public function testSettingsAreKeptAsOneJsonObject(): void {
		$widget = new Widget();
		$widget->setSettingsArray(['text' => 'Projects', 'showTitle' => false, 'level' => 2, 'ratio' => 1.5, 'color' => null]);

		$this->assertSame('{"text":"Projects","showTitle":false,"level":2,"ratio":1.5,"color":null}', $widget->getSettings());
		$this->assertSame(['text' => 'Projects', 'showTitle' => false, 'level' => 2, 'ratio' => 1.5, 'color' => null], $widget->getSettingsArray());
	}

	public function testNoSettingsAreStoredAsAnEmptyObject(): void {
		$widget = new Widget();
		$widget->setSettingsArray([]);

		$this->assertSame('{}', $widget->getSettings());
		$this->assertSame([], $widget->getSettingsArray());
	}

	public function testNoStoredValueReadsAsNoSettings(): void {
		$this->assertSame([], (new Widget())->getSettingsArray());
	}

	/**
	 * A stored value that is not a JSON object reads as no settings rather than failing.
	 */
	public function testAStoredValueThatIsNotAnObjectReadsAsNoSettings(): void {
		$widget = new Widget();
		foreach (['', '"text"', '42', 'null', '{broken'] as $stored) {
			$widget->setSettings($stored);
			$this->assertSame([], $widget->getSettingsArray(), $stored);
		}
	}

	public function testSettingsSerialiseAsAJsonObjectAlsoWhenEmpty(): void {
		$widget = new Widget();
		$widget->setSettingsArray([]);
		$this->assertSame('{}', json_encode($widget->jsonSerialize()['settings']));

		$widget->setSettingsArray(['text' => 'Projects']);
		$this->assertSame('{"text":"Projects"}', json_encode($widget->jsonSerialize()['settings']));
	}

	/**
	 * Databases may hand integers back as strings; the entities cast them.
	 */
	public function testGridValuesAndIdsComeBackAsIntegers(): void {
		$widget = Widget::fromRow(['id' => '7', 'page_id' => '3', 'grid_x' => '1', 'grid_y' => '20', 'grid_width' => '6', 'grid_height' => '4']);
		$this->assertSame([7, 3, 1, 20, 6, 4], [$widget->getId(), $widget->getPageId(), $widget->getGridX(), $widget->getGridY(), $widget->getGridWidth(), $widget->getGridHeight()]);

		$source = WidgetSource::fromRow(['widget_id' => '7', 'node_rel_id' => '12']);
		$this->assertSame([7, 12], [$source->getWidgetId(), $source->getNodeRelId()]);
	}
}
