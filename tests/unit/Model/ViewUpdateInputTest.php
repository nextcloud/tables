<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use InvalidArgumentException;
use OCA\Tables\Constants\ViewUpdatableParameters;
use OCA\Tables\Model\ViewSettings;
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

	public function testAGridThatIsNotAnObjectIsRejected(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('"grid" must be an object');
		ViewUpdateInput::fromInputArray(['grid' => 'not json']);
	}

	public function testUpdateDetailCarriesAKnownLayout(): void {
		$input = ViewUpdateInput::fromInputArray(['layout' => 'gallery']);

		$this->assertSame('gallery', $this->updates($input)[ViewUpdatableParameters::LAYOUT->value]);
	}

	public function testUpdateDetailOmitsAnAbsentLayout(): void {
		$this->assertArrayNotHasKey(
			ViewUpdatableParameters::LAYOUT->value,
			$this->updates(ViewUpdateInput::fromInputArray(['layout' => ''])),
		);
	}

	public function testAnUnknownLayoutIsRejected(): void {
		$this->expectException(InvalidArgumentException::class);

		ViewUpdateInput::fromInputArray(['layout' => 'carousel']);
	}

	public function testALayoutThatIsNotAStringIsRejected(): void {
		$this->expectException(InvalidArgumentException::class);

		ViewUpdateInput::fromInputArray(['layout' => 1]);
	}

	public function testViewSettingsArriveAsJsonFromTheClient(): void {
		$input = ViewUpdateInput::fromInputArray([
			'viewSettings' => json_encode(['cardBackgroundSource' => 3, 'cardTitleSource' => null]),
		]);

		$viewSettings = $this->updates($input)[ViewUpdatableParameters::VIEW_SETTINGS->value];
		$this->assertInstanceOf(ViewSettings::class, $viewSettings);
		$this->assertSame(3, $viewSettings->getCardBackgroundSource());
		$this->assertNull($viewSettings->getCardTitleSource());
	}

	public function testNullViewSettingsClearTheStoredSources(): void {
		// A cleared setting still has to reach the view, so it is an empty value object
		// rather than the absent case that updateDetail() skips.
		$input = ViewUpdateInput::fromInputArray(['viewSettings' => null]);

		$viewSettings = $this->updates($input)[ViewUpdatableParameters::VIEW_SETTINGS->value];
		$this->assertNull($viewSettings->getCardBackgroundSource());
		$this->assertNull($viewSettings->getCardTitleSource());
	}

	public function testViewSettingsThatAreNotAStructureAreRejected(): void {
		$this->expectException(InvalidArgumentException::class);

		ViewUpdateInput::fromInputArray(['viewSettings' => 5]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function updates(ViewUpdateInput $input): array {
		$updates = [];
		foreach ($input->updateDetail() as $parameter => $value) {
			$updates[$parameter->value] = $value;
		}

		return $updates;
	}
}
