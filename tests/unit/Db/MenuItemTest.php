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
			'technicalName' => '',
			'order' => 0,
			'parentId' => null,
			'broken' => false,
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
		$item->setParentId(4);

		$json = $item->jsonSerialize();

		$this->assertSame(9, $json['id']);
		$this->assertSame('Intake home', $json['label']);
		$this->assertSame('view', $json['targetType']);
		$this->assertSame(6, $json['targetId']);
		$this->assertSame('home', $json['technicalName']);
		$this->assertSame(20, $json['order']);
		$this->assertSame(4, $json['parentId']);
	}

	public static function targetTypeProvider(): array {
		return [
			'view' => [MenuItem::TARGET_VIEW],
			'table' => [MenuItem::TARGET_TABLE],
			'url' => [MenuItem::TARGET_URL],
		];
	}

	/**
	 * @dataProvider targetTypeProvider
	 */
	public function testJsonSerializeKeepsEachTargetType(string $targetType): void {
		$item = new MenuItem();
		$item->setTargetType($targetType);

		$this->assertSame($targetType, $item->jsonSerialize()['targetType']);
	}

	public function testBrokenIsReportedButNeverStored(): void {
		$item = new MenuItem();
		$item->setBroken(true);

		$this->assertTrue($item->jsonSerialize()['broken']);
		$this->assertArrayNotHasKey('broken', $item->getUpdatedFields());
	}

	public function testTargetTypesAreTheThreeKnownOnes(): void {
		$this->assertSame(['view', 'table', 'url'], MenuItem::TARGET_TYPES);
	}
}
