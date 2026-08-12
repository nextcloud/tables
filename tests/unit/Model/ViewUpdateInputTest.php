<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use OCA\Tables\Constants\ViewUpdatableParameters;
use OCA\Tables\Model\ViewUpdateInput;
use PHPUnit\Framework\TestCase;

class ViewUpdateInputTest extends TestCase {

	public function testUpdateDetailIncludesDescription(): void {
		$input = ViewUpdateInput::fromInputArray(['description' => 'Imported view description']);
		$updates = [];

		foreach ($input->updateDetail() as $parameter => $value) {
			$updates[$parameter->value] = $value;
		}

		$this->assertSame('Imported view description', $updates[ViewUpdatableParameters::DESCRIPTION->value]);
	}

	public function testUpdateDetailIncludesEmptyDescription(): void {
		$input = ViewUpdateInput::fromInputArray(['description' => '']);
		$updates = [];

		foreach ($input->updateDetail() as $parameter => $value) {
			$updates[$parameter->value] = $value;
		}

		$this->assertArrayHasKey(ViewUpdatableParameters::DESCRIPTION->value, $updates);
		$this->assertSame('', $updates[ViewUpdatableParameters::DESCRIPTION->value]);
	}
}
