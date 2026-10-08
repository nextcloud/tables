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
	public function testGridArrayIsEmptyWhenNothingIsStored(): void {
		$view = new View();
		$this->assertSame(['widgets' => [], 'layout' => []], $view->getGridArray());

		$view->setGrid('null');
		$this->assertSame(['widgets' => [], 'layout' => []], $view->getGridArray());
	}

	public function testGridArrayDropsUnknownKeysAndNonLists(): void {
		$view = new View();
		$view->setGrid(json_encode(['widgets' => 'oops', 'layout' => [['widgetId' => 'w-1']], 'extra' => true]));

		$this->assertSame(['widgets' => [], 'layout' => [['widgetId' => 'w-1']]], $view->getGridArray());
	}

	public function testGridArrayRoundTrips(): void {
		$grid = [
			'widgets' => [['id' => 'w-1', 'type' => 'header', 'configuration' => ['title' => 'Hi', 'showTitle' => false], 'content' => ['title' => 'Hi']]],
			'layout' => [['id' => 1, 'widgetId' => 'w-1', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2]],
		];
		$view = new View();
		$view->setGridArray($grid);

		$this->assertSame($grid, $view->getGridArray());
	}

	public function testGridArrayUpgradesWidgetsSavedBeforeTheConfiguration(): void {
		$view = new View();
		$view->setGrid(json_encode(['widgets' => [['id' => 'w-1', 'type' => 'text', 'title' => 'How', 'showTitle' => true, 'content' => ['text' => 'x']]], 'layout' => []]));

		$this->assertSame([['id' => 'w-1', 'type' => 'text', 'content' => ['text' => 'x'], 'configuration' => ['title' => 'How', 'showTitle' => true]]], $view->getGridArray()['widgets']);
	}

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

	public function testJsonSerializeCarriesTypeGridAndTechnicalName(): void {
		$view = new View();
		$view->setType(View::TYPE_GRID);
		$view->setTechnicalName('home');
		$view->setCreatedBy('alice');
		$view->setGridArray(['widgets' => [], 'layout' => []]);

		$json = $view->jsonSerialize();

		$this->assertSame('grid', $json['type']);
		$this->assertSame('home', $json['technicalName']);
		$this->assertSame(['widgets' => [], 'layout' => []], $json['grid']);
		$this->assertSame(-1, $json['tableId']);
		$this->assertSame('alice', $json['ownership']);
	}
}
