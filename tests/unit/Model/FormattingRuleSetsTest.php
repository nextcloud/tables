<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use OCA\Tables\Constants\SelectionReference;
use OCA\Tables\Db\Column;
use OCA\Tables\Model\FormattingRuleSets;
use PHPUnit\Framework\TestCase;

class FormattingRuleSetsTest extends TestCase {
	public function testResolvableReferencesAreTranslated(): void {
		$result = FormattingRuleSets::remapIds(
			[self::ruleSet(targetCol: 3, columnId: 3, value: SelectionReference::PREFIX . '7')],
			[3 => 42],
			[3 => [7 => 99]],
		);

		$this->assertSame(42, $result[0]['targetCol']);
		$this->assertSame(42, $result[0]['rules'][0]['condition']['groups'][0]['conditions'][0]['columnId']);
		$this->assertSame(
			SelectionReference::PREFIX . '99',
			$result[0]['rules'][0]['condition']['groups'][0]['conditions'][0]['value'],
		);
		$this->assertFalse($result[0]['broken']);
		$this->assertFalse($result[0]['rules'][0]['broken']);
	}

	public function testMetaColumnReferencesSurviveUntouched(): void {
		$result = FormattingRuleSets::remapIds(
			[self::ruleSet(targetCol: Column::TYPE_META_CREATED_BY, columnId: Column::TYPE_META_ID)],
			[],
			[],
		);

		$this->assertSame(Column::TYPE_META_CREATED_BY, $result[0]['targetCol']);
		$this->assertSame(
			Column::TYPE_META_ID,
			$result[0]['rules'][0]['condition']['groups'][0]['conditions'][0]['columnId'],
		);
		$this->assertFalse($result[0]['broken']);
		$this->assertFalse($result[0]['rules'][0]['broken']);
	}

	public function testUnresolvableColumnReferencesAreNeutralisedNotLeftStale(): void {
		$result = FormattingRuleSets::remapIds(
			[self::ruleSet(targetCol: 3, columnId: 3)],
			[],
			[],
		);

		$this->assertSame(FormattingRuleSets::UNRESOLVED_ID, $result[0]['targetCol']);
		$this->assertSame(
			FormattingRuleSets::UNRESOLVED_ID,
			$result[0]['rules'][0]['condition']['groups'][0]['conditions'][0]['columnId'],
		);
		$this->assertTrue($result[0]['broken']);
		$this->assertTrue($result[0]['rules'][0]['broken']);
	}

	public function testUnresolvableSelectionOptionsAreNeutralisedNotLeftStale(): void {
		$result = FormattingRuleSets::remapIds(
			[self::ruleSet(targetCol: null, columnId: 3, value: SelectionReference::PREFIX . '7')],
			[3 => 42],
			[3 => [8 => 99]],
		);

		$this->assertSame(
			SelectionReference::PREFIX . FormattingRuleSets::UNRESOLVED_OPTION_ID,
			$result[0]['rules'][0]['condition']['groups'][0]['conditions'][0]['value'],
		);
		$this->assertTrue($result[0]['rules'][0]['broken']);
	}

	public function testContainsItemObjectsAreNeutralisedTheSameWay(): void {
		$result = FormattingRuleSets::remapIds(
			[self::ruleSet(targetCol: null, columnId: 3, value: [['id' => 7, 'label' => 'Gone']])],
			[3 => 42],
			[3 => [8 => 99]],
		);

		$value = $result[0]['rules'][0]['condition']['groups'][0]['conditions'][0]['value'];
		$this->assertSame(FormattingRuleSets::UNRESOLVED_OPTION_ID, $value[0]['id']);
		$this->assertTrue($result[0]['rules'][0]['broken']);
	}

	public function testOptionSentinelCannotBeAliveOption(): void {
		$this->assertLessThan(0, FormattingRuleSets::UNRESOLVED_OPTION_ID);
		$this->assertNotSame(FormattingRuleSets::UNRESOLVED_ID, FormattingRuleSets::UNRESOLVED_OPTION_ID);
	}

	public function testOptionsOfAColumnTheImportDidNotCreateAreLeftAlone(): void {
		$result = FormattingRuleSets::remapIds(
			[self::ruleSet(targetCol: null, columnId: 3, value: SelectionReference::PREFIX . '7')],
			[3 => 3],
			[],
		);

		$this->assertSame(
			SelectionReference::PREFIX . '7',
			$result[0]['rules'][0]['condition']['groups'][0]['conditions'][0]['value'],
		);
		$this->assertFalse($result[0]['rules'][0]['broken']);
	}

	private static function ruleSet(?int $targetCol, int $columnId, mixed $value = 5): array {
		return [
			'id' => 'rs-1',
			'title' => 'RS',
			'targetType' => $targetCol === null ? 'row' : 'column',
			'targetCol' => $targetCol,
			'mode' => 'first-match',
			'sortOrder' => 0,
			'enabled' => true,
			'broken' => false,
			'rules' => [[
				'id' => 'rule-1',
				'title' => 'R',
				'sortOrder' => 0,
				'enabled' => true,
				'broken' => false,
				'condition' => ['groups' => [['conditions' => [[
					'columnId' => $columnId,
					'columnType' => 'selection',
					'operator' => 'is-equal',
					'value' => $value,
				]]]]],
				'format' => ['backgroundColor' => '#ff0000'],
			]],
		];
	}
}
