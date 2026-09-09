<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase {
	public function testAViewWithoutALayoutRendersAsATable(): void {
		$this->assertSame('table', (new View())->getLayoutNormalized());
	}

	public function testAStoredLayoutIsKept(): void {
		$view = new View();
		$view->setLayout('gallery');

		$this->assertSame('gallery', $view->getLayoutNormalized());
	}

	/**
	 * A row may hold a layout only a newer version knows, so it renders as a table rather
	 * than as nothing at all.
	 */
	public function testAnUnknownStoredLayoutRendersAsATable(): void {
		$view = new View();
		$view->setLayout('carousel');

		$this->assertSame('table', $view->getLayoutNormalized());
	}

	public function testStoredCardSourcesAreRead(): void {
		$view = new View();
		$view->setViewSettings(json_encode(['cardBackgroundSource' => 3, 'cardTitleSource' => 4]));

		$viewSettings = $view->getViewSettingsObject();
		$this->assertSame(3, $viewSettings->getCardBackgroundSource());
		$this->assertSame(4, $viewSettings->getCardTitleSource());
	}

	/**
	 * @dataProvider provideUnusableViewSettings
	 */
	public function testUnusableViewSettingsReadAsNoSources(?string $stored): void {
		// One malformed row must not fail every endpoint that serialises the view.
		$view = new View();
		$view->setViewSettings($stored);

		$viewSettings = $view->getViewSettingsObject();
		$this->assertNull($viewSettings->getCardBackgroundSource());
		$this->assertNull($viewSettings->getCardTitleSource());
	}

	/**
	 * @return array<string, array{?string}>
	 */
	public static function provideUnusableViewSettings(): array {
		return [
			'never written' => [null],
			'empty' => [''],
			'json null' => ['null'],
			'a scalar rather than an object' => ['5'],
			'a string rather than an object' => ['"gallery"'],
			'sources of the wrong type' => ['{"cardBackgroundSource":"3","cardTitleSource":[]}'],
		];
	}
}
