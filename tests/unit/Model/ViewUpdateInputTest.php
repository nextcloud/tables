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

	public function testUpdateDetailIncludesTypeGridAndTechnicalName(): void {
		$grid = ['widgets' => [['id' => 'w-1', 'type' => 'header']], 'layout' => [['id' => 1, 'widgetId' => 'w-1', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2]]];
		$input = ViewUpdateInput::fromInputArray(['type' => 'grid', 'grid' => json_encode($grid), 'technicalName' => 'home']);
		$updates = [];

		foreach ($input->updateDetail() as $parameter => $value) {
			$updates[$parameter->value] = $value;
		}

		$this->assertSame('grid', $updates[ViewUpdatableParameters::TYPE->value]);
		$this->assertSame($grid, $updates[ViewUpdatableParameters::GRID->value]);
		$this->assertSame('home', $updates[ViewUpdatableParameters::TECHNICAL_NAME->value]);
	}

	public function testUpdateDetailSkipsGridWhenAbsent(): void {
		$input = ViewUpdateInput::fromInputArray(['title' => 'Only a title']);
		$updates = [];

		foreach ($input->updateDetail() as $parameter => $value) {
			$updates[$parameter->value] = $value;
		}

		$this->assertArrayNotHasKey(ViewUpdatableParameters::GRID->value, $updates);
		$this->assertArrayNotHasKey(ViewUpdatableParameters::TYPE->value, $updates);
		$this->assertArrayNotHasKey(ViewUpdatableParameters::TECHNICAL_NAME->value, $updates);
	}
}
