<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use InvalidArgumentException;
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

	public function testUpdateDetailIncludesTypeAndTechnicalName(): void {
		$input = ViewUpdateInput::fromInputArray(['type' => 'grid', 'technicalName' => 'home']);
		$updates = [];

		foreach ($input->updateDetail() as $parameter => $value) {
			$updates[$parameter->value] = $value;
		}

		$this->assertSame('grid', $updates[ViewUpdatableParameters::TYPE->value]);
		$this->assertSame('home', $updates[ViewUpdatableParameters::TECHNICAL_NAME->value]);
	}

	public function testUpdateDetailSkipsTypeWhenAbsent(): void {
		$input = ViewUpdateInput::fromInputArray(['title' => 'Only a title']);
		$updates = [];

		foreach ($input->updateDetail() as $parameter => $value) {
			$updates[$parameter->value] = $value;
		}

		$this->assertArrayNotHasKey(ViewUpdatableParameters::TYPE->value, $updates);
		$this->assertArrayNotHasKey(ViewUpdatableParameters::TECHNICAL_NAME->value, $updates);
	}

	public function testATypeThatIsNotAStringIsRejected(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('"type" must be a string');
		ViewUpdateInput::fromInputArray(['type' => ['grid']]);
	}

	public function testAnUnknownTypeIsRejected(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown view type "kanban"');
		ViewUpdateInput::fromInputArray(['type' => 'kanban']);
	}
}
