<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Constants;

use OCA\Tables\Constants\ViewLayout;
use PHPUnit\Framework\TestCase;

class ViewLayoutTest extends TestCase {
	public function testTryFromMixedResolvesAKnownLayout(): void {
		$this->assertSame(ViewLayout::GALLERY, ViewLayout::tryFromMixed('gallery'));
	}

	/**
	 * @dataProvider provideValuesThatNameNoLayout
	 */
	public function testTryFromMixedDropsWhatNamesNoLayout(mixed $value): void {
		$this->assertNull(ViewLayout::tryFromMixed($value));
	}

	/**
	 * @dataProvider provideValuesThatNameNoLayout
	 */
	public function testNormalizeFallsBackToTheTableLayout(mixed $value): void {
		$this->assertSame(ViewLayout::TABLE, ViewLayout::normalize($value));
	}

	public function testNormalizeKeepsAKnownLayout(): void {
		$this->assertSame(ViewLayout::TILES, ViewLayout::normalize('tiles'));
	}

	/**
	 * A layout reaches us from a scheme, a federated instance and a stored row alike, so
	 * everything a newer version or a hand written payload may put there has to be handled.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function provideValuesThatNameNoLayout(): array {
		return [
			'null' => [null],
			'empty string' => [''],
			'unknown name' => ['carousel'],
			'integer' => [1],
			'boolean' => [true],
			'array' => [['gallery']],
		];
	}
}
