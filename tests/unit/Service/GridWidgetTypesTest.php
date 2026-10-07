<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Service\GridWidgetTypes;
use PHPUnit\Framework\TestCase;

class GridWidgetTypesTest extends TestCase {
	public function testEveryTypeDescribesItselfAndItsProperties(): void {
		foreach (GridWidgetTypes::all() as $type => $definition) {
			$this->assertSame($type, $definition['type']);
			$this->assertNotSame('', $definition['title']);
			$this->assertGreaterThan(0, $definition['defaultWidth']);
			$this->assertGreaterThan(0, $definition['defaultHeight']);
			foreach ($definition['properties'] as $name => $property) {
				$this->assertContains($property['type'], GridWidgetTypes::PROPERTY_TYPES, $type . '.' . $name);
				$this->assertArrayHasKey('default', $property, $type . '.' . $name);
				if ($property['type'] === 'enum') {
					$this->assertContains($property['default'], array_column($property['options'], 'value'), $type . '.' . $name);
				}
			}
		}
	}

	public function testUnknownTypeIsRejected(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Unknown widget type');
		GridWidgetTypes::get('kanban');
	}

	public function testSanitizeFillsDefaultsAndDropsUnknownProperties(): void {
		$content = GridWidgetTypes::sanitizeContent('header', ['title' => 'Hi', 'stray' => true]);

		$this->assertSame(['title' => 'Hi', 'subtitle' => '', 'textAlign' => 'left', 'backgroundColor' => '', 'textColor' => ''], $content);
	}

	public function testSanitizeRejectsAMissingRequiredString(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('header.title is required');
		GridWidgetTypes::sanitizeContent('header', ['title' => '   ']);
	}

	public function testSanitizeRejectsANonHexColor(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('must be a hex color');
		GridWidgetTypes::sanitizeContent('header', ['title' => 'Hi', 'backgroundColor' => 'red']);
	}

	public function testSanitizeRejectsAnUnknownEnumValue(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('textAlign must be one of');
		GridWidgetTypes::sanitizeContent('header', ['title' => 'Hi', 'textAlign' => 'justify']);
	}

	public function testSanitizeKeepsAValidTarget(): void {
		$content = GridWidgetTypes::sanitizeContent('data', ['target' => ['type' => 'view', 'id' => 6, 'extra' => 1]]);

		$this->assertSame(['target' => ['type' => 'view', 'id' => 6]], $content);
	}

	public function testSanitizeUpgradesTheOldDataShape(): void {
		$content = GridWidgetTypes::sanitizeContent('data', ['targetType' => 'table', 'targetId' => '4']);

		$this->assertSame(['target' => ['type' => 'table', 'id' => 4]], $content);
	}

	public function testSanitizeRejectsATargetWithoutId(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('name a table or view by id');
		GridWidgetTypes::sanitizeContent('data', ['target' => ['type' => 'table']]);
	}

	public function testSanitizeRejectsAMissingRequiredTarget(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('data.target is required');
		GridWidgetTypes::sanitizeContent('data', []);
	}

	public function testSanitizeRejectsANonStringText(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('text.text must be a string');
		GridWidgetTypes::sanitizeContent('text', ['text' => ['nested']]);
	}
}
