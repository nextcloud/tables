<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Widget;
use PHPUnit\Framework\TestCase;

class WidgetTest extends TestCase {
	public static function storedSettings(): array {
		return [
			'an object, ordered by name' => ['{"text":"Projects","showTitle":false,"level":2}', '{"level":2,"showTitle":false,"text":"Projects"}'],
			'no settings' => [null, '{}'],
			'not an object' => ['"text"', '{}'],
			'broken' => ['{broken', '{}'],
		];
	}

	/**
	 * @dataProvider storedSettings
	 */
	public function testSettingsSerialiseAsAJsonObject(?string $stored, string $serialised): void {
		$widget = new Widget();
		$widget->setSettings($stored);

		$this->assertSame($serialised, json_encode($widget->jsonSerialize()['settings']));
	}
}
