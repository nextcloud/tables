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
	public function testSettingsSerialiseAsAJsonObjectAlsoWhenEmpty(): void {
		$widget = new Widget();
		$this->assertSame('{}', json_encode($widget->jsonSerialize()['settings']));

		$widget->setSettings([]);
		$this->assertSame('{}', json_encode($widget->jsonSerialize()['settings']));

		$widget->setSettings(['text' => 'Projects', 'showTitle' => false]);
		$this->assertSame('{"showTitle":false,"text":"Projects"}', json_encode($widget->jsonSerialize()['settings']));
	}
}
