<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\MenuItem;
use PHPUnit\Framework\TestCase;

class MenuItemTest extends TestCase {
	public function testJsonSerializeUsesSafeDefaults(): void {
		$item = new MenuItem();
		$item->setContextId(3);

		$this->assertSame([
			'id' => null,
			'contextId' => 3,
			'label' => '',
			'icon' => null,
			'targetType' => MenuItem::TARGET_URL,
			'targetId' => null,
			'url' => null,
			'technicalName' => null,
			'order' => 0,
		], $item->jsonSerialize());
	}

	public function testJsonSerializeCarriesEveryField(): void {
		$item = new MenuItem();
		$item->setId(9);
		$item->setContextId(3);
		$item->setLabel('Intake home');
		$item->setIcon('home');
		$item->setTargetType(MenuItem::TARGET_VIEW);
		$item->setTargetId(6);
		$item->setTechnicalName('home');
		$item->setOrder(20);

		$json = $item->jsonSerialize();

		$this->assertSame(9, $json['id']);
		$this->assertSame('Intake home', $json['label']);
		$this->assertSame('view', $json['targetType']);
		$this->assertSame(6, $json['targetId']);
		$this->assertSame('home', $json['technicalName']);
		$this->assertSame(20, $json['order']);
	}

	public function testTargetTypesAreTheThreeKnownOnes(): void {
		$this->assertSame(['view', 'table', 'url'], MenuItem::TARGET_TYPES);
	}
}
