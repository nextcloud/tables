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
	public function testTypeFallsBackToTable(): void {
		$view = new View();
		$this->assertSame(View::TYPE_TABLE, $view->getTypeOrDefault());

		$view->setType('something-else');
		$this->assertSame(View::TYPE_TABLE, $view->getTypeOrDefault());

		$view->setType(View::TYPE_GRID);
		$this->assertSame(View::TYPE_GRID, $view->getTypeOrDefault());
	}

	public function testOwnershipFallsBackToCreatorForViewsWithoutTable(): void {
		$view = new View();
		$view->setCreatedBy('alice');
		$this->assertSame('alice', $view->getOwnership());

		$view->setOwnership('table-owner');
		$this->assertSame('table-owner', $view->getOwnership());
	}

	public function testOwnershipDoesNotFallBackToCreatorForAViewWithATable(): void {
		$view = new View();
		$view->setTableId(4);
		$view->setCreatedBy('alice');

		$this->assertNull($view->getOwnership(), 'without the joined table owner the creator must not become the owner');
	}

	public function testOwnershipDoesNotFallBackToCreatorForAFederatedView(): void {
		$view = new View();
		$view->setExternalId(17);
		$view->setCreatedBy('remote-sharer');

		$this->assertNull($view->getOwnership());
	}

	public function testOnlyAViewWithoutTableAndExternalIdIsStandalone(): void {
		$view = new View();
		$this->assertTrue($view->isStandalone());

		$view->setExternalId(17);
		$this->assertFalse($view->isStandalone(), 'a federated view has no local table but is not standalone');

		$view->setExternalId(null);
		$view->setTableId(4);
		$this->assertFalse($view->isStandalone());
	}

	public function testJsonSerializeCarriesTypeAndTechnicalName(): void {
		$view = new View();
		$view->setType(View::TYPE_GRID);
		$view->setTechnicalName('home');
		$view->setCreatedBy('alice');

		$json = $view->jsonSerialize();

		$this->assertSame('grid', $json['type']);
		$this->assertSame('home', $json['technicalName']);
		$this->assertSame(-1, $json['tableId']);
		$this->assertSame('alice', $json['ownership']);
	}

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
