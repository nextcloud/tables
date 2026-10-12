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
}
