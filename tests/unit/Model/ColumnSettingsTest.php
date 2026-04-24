<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use OCA\Tables\Model\ColumnSettings;
use OCA\Tables\Service\ValueObject\ColumnOrderInformation;
use OCA\Tables\Service\ValueObject\ViewColumnInformation;
use PHPUnit\Framework\TestCase;

class ColumnSettingsTest extends TestCase {

	public function testCreateFromInputArrayWithValidData(): void {
		$data = [
			['columnId' => 1, 'order' => 0, 'readonly' => false],
			['columnId' => 2, 'order' => 1, 'readonly' => true],
		];
		$settings = ColumnSettings::createFromInputArray($data);
		$serialized = $settings->jsonSerialize();

		$this->assertCount(2, $serialized);
		$this->assertSame(1, $serialized[0]['columnId']);
		$this->assertSame(0, $serialized[0]['order']);
		$this->assertSame(2, $serialized[1]['columnId']);
		$this->assertSame(1, $serialized[1]['order']);
	}

	public function testCreateFromInputArrayProducesColumnOrderInformation(): void {
		$settings = ColumnSettings::createFromInputArray([
			['columnId' => 1, 'order' => 0],
		]);
		$entries = iterator_to_array($settings->columnInformation());
		$this->assertInstanceOf(ColumnOrderInformation::class, $entries[0]);
		$this->assertNotInstanceOf(ViewColumnInformation::class, $entries[0]);
	}

	public function testCreateFromInputArrayWithEmptyArray(): void {
		$settings = ColumnSettings::createFromInputArray([]);
		$this->assertSame([], $settings->jsonSerialize());
	}

	public function testCreateFromInputArrayThrowsWhenEntryIsNotAnArray(): void {
		$this->expectException(\InvalidArgumentException::class);
		ColumnSettings::createFromInputArray(['not-an-array']);
	}

	public function testCreateFromInputArrayThrowsWhenEntryIsAnInteger(): void {
		$this->expectException(\InvalidArgumentException::class);
		ColumnSettings::createFromInputArray([42]);
	}

	public function testCreateFromInputArrayThrowsWhenColumnIdIsMissing(): void {
		$this->expectException(\InvalidArgumentException::class);
		ColumnSettings::createFromInputArray([['order' => 0]]);
	}

	public function testCreateFromInputArrayThrowsWhenOrderIsMissing(): void {
		$this->expectException(\InvalidArgumentException::class);
		ColumnSettings::createFromInputArray([['columnId' => 1]]);
	}

	public function testCreateViewSettingsFromInputArrayProducesViewColumnInformation(): void {
		$data = [
			['columnId' => 1, 'order' => 0, 'readonly' => true, 'mandatory' => false],
			['columnId' => 2, 'order' => 1, 'readonly' => false, 'mandatory' => true],
		];
		$settings = ColumnSettings::createViewSettingsFromInputArray($data);
		$entries = iterator_to_array($settings->columnInformation());

		$this->assertInstanceOf(ViewColumnInformation::class, $entries[0]);
		$this->assertTrue($entries[0]->isReadonly());
		$this->assertFalse($entries[0]->isMandatory());
		$this->assertInstanceOf(ViewColumnInformation::class, $entries[1]);
		$this->assertFalse($entries[1]->isReadonly());
		$this->assertTrue($entries[1]->isMandatory());
	}

	public function testCreateViewSettingsFromInputArraySerialisesAllFields(): void {
		$data = [['columnId' => 3, 'order' => 2, 'readonly' => true, 'mandatory' => true]];
		$settings = ColumnSettings::createViewSettingsFromInputArray($data);
		$serialized = $settings->jsonSerialize();

		$this->assertSame(3, $serialized[0]['columnId']);
		$this->assertSame(2, $serialized[0]['order']);
		$this->assertTrue($serialized[0]['readonly']);
		$this->assertTrue($serialized[0]['mandatory']);
	}

	public function testCreateViewSettingsFromInputArrayThrowsWhenEntryIsNotAnArray(): void {
		$this->expectException(\InvalidArgumentException::class);
		ColumnSettings::createViewSettingsFromInputArray(['not-an-array']);
	}

	public function testCreateViewSettingsFromInputArrayThrowsWhenColumnIdIsMissing(): void {
		$this->expectException(\InvalidArgumentException::class);
		ColumnSettings::createViewSettingsFromInputArray([['order' => 0]]);
	}
}
