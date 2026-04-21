<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use OCA\Tables\Model\ColumnSettings;
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
}
