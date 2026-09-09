<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use InvalidArgumentException;
use OCA\Tables\Model\ViewSettings;
use PHPUnit\Framework\TestCase;

class ViewSettingsTest extends TestCase {
	public function testInputArrayKeepsIntegerSources(): void {
		$settings = ViewSettings::createFromInputArray(['cardBackgroundSource' => 3, 'cardTitleSource' => 4]);

		$this->assertSame(3, $settings->getCardBackgroundSource());
		$this->assertSame(4, $settings->getCardTitleSource());
	}

	public function testInputArrayRejectsNonIntegerSource(): void {
		$this->expectException(InvalidArgumentException::class);

		ViewSettings::createFromInputArray(['cardBackgroundSource' => '3']);
	}

	public function testInputArrayResolvesUuidThroughTheColumnsMap(): void {
		// Only getId() is read from the map, and a real Column needs the server to load.
		$column = new class {
			public function getId(): int {
				return 42;
			}
		};

		$settings = ViewSettings::createFromInputArray(
			['cardBackgroundSourceUuid' => 'uuid-a', 'cardTitleSourceUuid' => 'uuid-missing'],
			['uuid-a' => $column],
		);

		$this->assertSame(42, $settings->getCardBackgroundSource());
		$this->assertNull($settings->getCardTitleSource(), 'a uuid that does not resolve is dropped');
	}

	public function testStoredArrayToleratesWhatItCannotUse(): void {
		$settings = ViewSettings::createFromStoredArray(['cardBackgroundSource' => 'garbage', 'cardTitleSource' => 7]);

		$this->assertNull($settings->getCardBackgroundSource());
		$this->assertSame(7, $settings->getCardTitleSource());
		$this->assertSame(
			['cardBackgroundSource' => null, 'cardTitleSource' => 7],
			$settings->jsonSerialize(),
		);
	}

	public function testRemapSourcesFollowsTheMapAndDropsTheRest(): void {
		$remapped = ViewSettings::remapSources(
			['cardBackgroundSource' => 1, 'cardTitleSource' => 2, 'other' => 'kept'],
			[1 => 10],
		);

		$this->assertSame(10, $remapped['cardBackgroundSource']);
		$this->assertNull($remapped['cardTitleSource'], 'an id without a mapping must not survive');
		$this->assertSame('kept', $remapped['other']);
	}

	public function testRemapSourcesLeavesUnsetAndNullAlone(): void {
		$this->assertSame(['cardTitleSource' => null], ViewSettings::remapSources(['cardTitleSource' => null], [1 => 10]));
		$this->assertSame([], ViewSettings::remapSources([], [1 => 10]));
	}

	public function testRemapSourcesDropsANonPositiveOrNonIntegerId(): void {
		$remapped = ViewSettings::remapSources(['cardBackgroundSource' => '1', 'cardTitleSource' => 0], [1 => 10, 0 => 5]);

		$this->assertNull($remapped['cardBackgroundSource']);
		$this->assertNull($remapped['cardTitleSource']);
	}
}
