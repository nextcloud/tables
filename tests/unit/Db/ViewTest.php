<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\View;
use OCA\Tables\Model\Permissions;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase {
	private const FILTER = [[['columnId' => 33, 'operator' => 'is-equal', 'value' => 'red']]];

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

	private function makeView(string $columns): View {
		$view = new View();
		$view->setColumns($columns);
		return $view;
	}

	public function testGetColumnsArrayEmptyReturnsEmpty(): void {
		$view = $this->makeView('');
		$this->assertSame([], $view->getColumnsArray());
	}

	public function testGetColumnsArrayLegacyPlainIdFormat(): void {
		// Legacy format: plain array of integer column IDs — order is derived from index
		$view = $this->makeView(\json_encode([10, 20, 30]));
		$this->assertSame([10, 20, 30], $view->getColumnsArray());
	}

	public function testGetColumnsArrayObjectFormatIsSortedByOrder(): void {
		$view = $this->makeView(\json_encode([
			['columnId' => 5, 'order' => 2, 'readonly' => false, 'mandatory' => false],
			['columnId' => 3, 'order' => 1, 'readonly' => false, 'mandatory' => false],
		]));
		$this->assertSame([3, 5], $view->getColumnsArray());
	}

	public function testJsonSerializeReturnsColumnIds(): void {
		$view = $this->makeView(\json_encode([
			['columnId' => 5, 'order' => 2, 'readonly' => false, 'mandatory' => false],
			['columnId' => 3, 'order' => 1, 'readonly' => false, 'mandatory' => false],
		]));
		$this->assertSame([3, 5], $view->jsonSerialize()['columns']);
	}

	private function makeFilteredView(array $sort = []): View {
		$view = new View();
		$view->setColumnsArray([32, 33]);
		$view->setFilterArray(self::FILTER);
		$view->setSortArray($sort);
		return $view;
	}

	private function makeSharedView(bool $manageTable, array $sort = []): View {
		$view = $this->makeFilteredView($sort);
		$view->setIsShared(true);
		$view->setOnSharePermissions(new Permissions(read: true, manageTable: $manageTable));
		return $view;
	}

	public function testOwnerSeesFilterAndSort(): void {
		$view = $this->makeFilteredView([['columnId' => 33, 'mode' => 'ASC']]);

		$serialised = $view->jsonSerialize();

		$this->assertSame(self::FILTER, $serialised['filter']);
		$this->assertSame([['columnId' => 33, 'mode' => 'ASC']], $serialised['sort']);
	}

	public function testRecipientWithoutTableManageGetsNoFilterDetails(): void {
		$serialised = $this->makeSharedView(false)->jsonSerialize();

		$this->assertSame([], $serialised['filter']);
	}

	/**
	 * The filter must survive serialisation: ViewMapper caches the entity for the
	 * whole request and RowService::findAllByView() builds its query from it, so a
	 * blanked filter would return rows the view excludes.
	 */
	public function testSerialisingForRecipientKeepsFilterOnEntity(): void {
		$view = $this->makeSharedView(false);

		$view->jsonSerialize();

		$this->assertSame(self::FILTER, $view->getFilterArray());
	}

	public function testRecipientWithTableManageSeesFilter(): void {
		$serialised = $this->makeSharedView(true)->jsonSerialize();

		$this->assertSame(self::FILTER, $serialised['filter']);
	}

	public function testRecipientKeepsSortOnVisibleAndMetaColumns(): void {
		$sort = [
			['columnId' => 33, 'mode' => 'ASC'],
			['columnId' => Column::TYPE_META_ID, 'mode' => 'DESC'],
		];

		$serialised = $this->makeSharedView(false, $sort)->jsonSerialize();

		$this->assertSame($sort, $serialised['sort']);
	}

	public function testRecipientGetsNoSortWhenItStartsOnHiddenColumn(): void {
		$sort = [
			['columnId' => 99, 'mode' => 'ASC'],
			['columnId' => 33, 'mode' => 'DESC'],
		];

		$serialised = $this->makeSharedView(false, $sort)->jsonSerialize();

		$this->assertSame([], $serialised['sort']);
	}

	public function testRecipientSortStopsAtFirstHiddenColumn(): void {
		$sort = [
			['columnId' => 33, 'mode' => 'DESC'],
			['columnId' => 99, 'mode' => 'ASC'],
			['columnId' => Column::TYPE_META_ID, 'mode' => 'DESC'],
		];

		$serialised = $this->makeSharedView(false, $sort)->jsonSerialize();

		$this->assertSame([['columnId' => 33, 'mode' => 'DESC']], $serialised['sort']);
	}
}
