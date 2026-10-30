<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use InvalidArgumentException;
use OCA\Tables\Constants\ViewUpdatableParameters;
use OCA\Tables\Model\ColumnSettings;
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

	public function testColumnsBecomeColumnSettingsInListOrder(): void {
		$columnSettings = $this->updates(ViewUpdateInput::fromInputArray(['columns' => [5, 3]]))[ViewUpdatableParameters::COLUMN_SETTINGS->value];

		$this->assertInstanceOf(ColumnSettings::class, $columnSettings);
		$this->assertSame([
			['columnId' => 5, 'order' => 0, 'readonly' => false, 'mandatory' => false],
			['columnId' => 3, 'order' => 1, 'readonly' => false, 'mandatory' => false],
		], $columnSettings->jsonSerialize());
	}

	public function testColumnsArriveAsJsonFromTheClient(): void {
		$columnSettings = $this->updates(ViewUpdateInput::fromInputArray(['columns' => '[5,3]']))[ViewUpdatableParameters::COLUMN_SETTINGS->value];

		$this->assertSame([5, 3], array_map(static fn (array $setting): int => $setting['columnId'], $columnSettings->jsonSerialize()));
	}

	public function testColumnSettingsTakePrecedenceOverColumns(): void {
		$columnSettings = $this->updates(ViewUpdateInput::fromInputArray([
			'columns' => [5, 3],
			'columnSettings' => [['columnId' => 7, 'order' => 0]],
		]))[ViewUpdatableParameters::COLUMN_SETTINGS->value];

		$this->assertSame([7], array_map(static fn (array $setting): int => $setting['columnId'], $columnSettings->jsonSerialize()));
	}

	public function testColumnsThatAreNotAListAreRejected(): void {
		$this->expectException(InvalidArgumentException::class);

		ViewUpdateInput::fromInputArray(['columns' => 'abc']);
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
