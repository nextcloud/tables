<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Dto;

use InvalidArgumentException;
use OCA\Tables\Db\Column;
use OCA\Tables\Dto\RelationSettings;
use PHPUnit\Framework\TestCase;

final class RelationSettingsTest extends TestCase {
	public function testColumnParsesTypedRelationSettingsWithoutChangingStoredSettings(): void {
		$settings = ['relationType' => 'view', 'targetId' => '3', 'labelColumn' => '20', 'targetSetBy' => 'owner', 'width' => 200];
		$column = new Column();
		$column->setCustomSettings(json_encode($settings));

		$relation = $column->getCustomSettingsObject(RelationSettings::class);

		$this->assertTrue($relation->isView());
		$this->assertSame(3, $relation->targetId);
		$this->assertSame(20, $relation->labelColumn);
		$this->assertSame('owner', $relation->targetSetBy);
		$this->assertSame($settings, $column->getCustomSettingsArray());
	}

	public function testTableRelationDefaultsToNoTargetAuthor(): void {
		$settings = RelationSettings::fromArray(['relationType' => 'table', 'targetId' => 2, 'labelColumn' => 20]);

		$this->assertFalse($settings->isView());
		$this->assertSame('', $settings->targetSetBy);
	}

	public function testInvalidSettingsAreRejected(): void {
		$valid = ['relationType' => 'table', 'targetId' => 2, 'labelColumn' => 20];
		$invalidSettings = [[], ['targetId' => 2, 'labelColumn' => 20]];
		foreach (['relationType' => ['invalid', null, 1], 'targetId' => [null, 0, -1, true, 1.5, 'invalid'], 'labelColumn' => [null, 0, -1, false, [], '1.5'], 'targetSetBy' => [1, []]] as $field => $values) {
			foreach ($values as $value) {
				$invalidSettings[] = array_replace($valid, [$field => $value]);
			}
		}
		foreach ($invalidSettings as $settings) {
			try {
				RelationSettings::fromArray($settings);
				$this->fail('Invalid relation settings were accepted: ' . json_encode($settings));
			} catch (InvalidArgumentException $e) {
				$this->assertNotEmpty($e->getMessage());
			}
		}
	}

	public function testSettingsClassesMustProvideAnArrayFactory(): void {
		$this->expectException(InvalidArgumentException::class);

		(new Column())->getCustomSettingsObject(\stdClass::class);
	}

	public function testUnconfiguredCustomSettingsHaveNoRelationTarget(): void {
		$this->assertNull(RelationSettings::fromCustomSettings([]));
		$this->assertNull(RelationSettings::fromCustomSettings(['width' => 200, 'showPreview' => true]));
	}

	public function testIncompleteRelationSettingsAreRejected(): void {
		$this->expectException(InvalidArgumentException::class);

		RelationSettings::fromCustomSettings(['relationType' => 'table', 'targetId' => 2]);
	}

	public function testConfiguredCustomSettingsProduceTypedRelationSettings(): void {
		$settings = RelationSettings::fromCustomSettings(['relationType' => 'table', 'targetId' => '2', 'labelColumn' => '20', 'width' => 200]);

		$this->assertInstanceOf(RelationSettings::class, $settings);
		$this->assertSame(2, $settings->targetId);
		$this->assertSame(20, $settings->labelColumn);
	}

	public function testTargetComparisonIgnoresTheAuthor(): void {
		$settings = new RelationSettings('table', 2, 20, 'owner');

		$this->assertTrue($settings->hasSameTarget(new RelationSettings('table', 2, 20, 'editor')));
		$this->assertFalse($settings->hasSameTarget(new RelationSettings('view', 2, 20, 'owner')));
		$this->assertFalse($settings->hasSameTarget(new RelationSettings('table', 3, 20, 'owner')));
		$this->assertFalse($settings->hasSameTarget(new RelationSettings('table', 2, 21, 'owner')));
	}
}
