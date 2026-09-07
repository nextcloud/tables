<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use InvalidArgumentException;
use OCA\Tables\Constants\FilterOperator;
use OCA\Tables\Model\FormattingConditionGroupInput;
use OCA\Tables\Model\FormattingConditionSetInput;
use OCA\Tables\Model\FormattingRuleInput;
use OCA\Tables\Model\FormattingRuleSetInput;
use OCA\Tables\Model\FormattingStyleInput;
use PHPUnit\Framework\TestCase;

class FormattingInputTest extends TestCase {
	public function testStyleRoundTripsAllKeys(): void {
		$style = FormattingStyleInput::createFromInputArray([
			'backgroundColor' => '#ff0000',
			'textColor' => '#fff',
			'fontWeight' => 'bold',
			'fontStyle' => 'italic',
			'textDecoration' => 'underline',
		]);

		$this->assertSame([
			'backgroundColor' => '#ff0000',
			'textColor' => '#fff',
			'fontWeight' => 'bold',
			'fontStyle' => 'italic',
			'textDecoration' => 'underline',
		], $style->toArray());
	}

	public function testEmptyStyleSerializesToEmptyArray(): void {
		$this->assertSame([], FormattingStyleInput::createFromInputArray([])->toArray());
	}

	public function testStyleRejectsUnknownKey(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown style key(s): border');
		FormattingStyleInput::createFromInputArray(['border' => '1px']);
	}

	/** @return iterable<string, array{array<string, string>}> */
	public static function invalidStyleProvider(): iterable {
		yield 'background without hash' => [['backgroundColor' => 'ff0000']];
		yield 'background too long' => [['backgroundColor' => '#ff00000']];
		yield 'text color not hex' => [['textColor' => '#gggggg']];
		yield 'font weight not bold' => [['fontWeight' => 'normal']];
		yield 'font style not italic' => [['fontStyle' => 'oblique']];
		yield 'text decoration unknown' => [['textDecoration' => 'overline']];
	}

	/** @dataProvider invalidStyleProvider */
	public function testStyleRejectsInvalidValues(array $input): void {
		$this->expectException(InvalidArgumentException::class);
		FormattingStyleInput::createFromInputArray($input);
	}

	/** @return iterable<string, array{string}> */
	public static function operatorProvider(): iterable {
		foreach (FilterOperator::cases() as $operator) {
			yield $operator->value => [$operator->value];
		}
	}

	/**
	 * Conditions are built with the view filter editor, so every operator that editor can
	 * produce has to be storable.
	 *
	 * @dataProvider operatorProvider
	 */
	public function testGroupAcceptsEveryFilterOperator(string $operator): void {
		$group = FormattingConditionGroupInput::createFromInputArray([
			'conditions' => [[
				'columnId' => '7',
				'columnType' => 'text-line',
				'operator' => $operator,
				'value' => 'needle',
			]],
		]);

		$condition = $group->toArray()['conditions'][0];
		$this->assertSame($operator, $condition['operator']);
		$this->assertSame(7, $condition['columnId'], 'columnId is cast to int');
	}

	public function testGroupRejectsOperatorsOutsideTheFilterVocabulary(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown operator: eq');
		FormattingConditionGroupInput::createFromInputArray([
			'conditions' => [['columnId' => 1, 'columnType' => 'text-line', 'operator' => 'eq', 'value' => 'x']],
		]);
	}

	/** @return iterable<string, array{string}> */
	public static function valuelessOperatorProvider(): iterable {
		yield 'is-empty' => ['is-empty'];
		yield 'is-not-empty' => ['is-not-empty'];
	}

	/** @dataProvider valuelessOperatorProvider */
	public function testGroupKeepsValuelessOperatorsWithoutAValue(string $operator): void {
		$group = FormattingConditionGroupInput::createFromInputArray([
			'conditions' => [['columnId' => 1, 'columnType' => 'text-line', 'operator' => $operator]],
		]);

		$this->assertArrayNotHasKey('value', $group->toArray()['conditions'][0]);
	}

	public function testGroupRejectsAValueTakingOperatorWithoutAValue(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Operator is-greater-than requires a value');
		FormattingConditionGroupInput::createFromInputArray([
			'conditions' => [['columnId' => 1, 'columnType' => 'number', 'operator' => 'is-greater-than']],
		]);
	}

	public function testGroupKeepsListValuesAndCollectsColumnIds(): void {
		$group = FormattingConditionGroupInput::createFromInputArray([
			'conditions' => [
				['columnId' => 1, 'columnType' => 'selection-multi', 'operator' => 'contains-item', 'value' => [3 => ['id' => 7, 'label' => 'a']]],
				['columnId' => 2, 'columnType' => 'selection', 'operator' => 'is-equal', 'value' => '@selection-id-1'],
			],
		]);

		$conditions = $group->toArray()['conditions'];
		$this->assertSame([['id' => 7, 'label' => 'a']], $conditions[0]['value'], 'list values are re-indexed');
		$this->assertSame('@selection-id-1', $conditions[1]['value']);
		$this->assertSame([1, 2], $group->collectColumnIds());
	}

	/** @return iterable<string, array{array<string, mixed>}> */
	public static function incompleteConditionProvider(): iterable {
		yield 'missing columnId' => [['columnType' => 'number', 'operator' => 'is-equal']];
		yield 'missing columnType' => [['columnId' => 1, 'operator' => 'is-equal']];
		yield 'missing operator' => [['columnId' => 1, 'columnType' => 'number']];
		yield 'null columnId' => [['columnId' => null, 'columnType' => 'number', 'operator' => 'is-equal']];
	}

	/** @dataProvider incompleteConditionProvider */
	public function testGroupRejectsIncompleteCondition(array $condition): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Condition requires columnId, columnType and operator');
		FormattingConditionGroupInput::createFromInputArray(['conditions' => [$condition]]);
	}

	public function testGroupRejectsNonArrayCondition(): void {
		$this->expectException(InvalidArgumentException::class);
		FormattingConditionGroupInput::createFromInputArray(['conditions' => ['not-an-array']]);
	}

	public function testGroupRejectsMissingConditionsKey(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('conditions must be an array');
		FormattingConditionGroupInput::createFromInputArray([]);
	}

	public function testGroupRejectsMoreThanTwentyConditions(): void {
		$conditions = array_fill(0, 21, ['columnId' => 1, 'columnType' => 'number', 'operator' => 'is-empty']);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Max 20 conditions per group');
		FormattingConditionGroupInput::createFromInputArray(['conditions' => $conditions]);
	}

	public function testConditionSetRequiresAtLeastOneGroup(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('At least one condition group is required');
		FormattingConditionSetInput::createFromInputArray(['groups' => []]);
	}

	public function testConditionSetRejectsMissingGroups(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('groups must be an array');
		FormattingConditionSetInput::createFromInputArray([]);
	}

	public function testConditionSetRejectsMoreThanTenGroups(): void {
		$group = ['conditions' => [['columnId' => 1, 'columnType' => 'number', 'operator' => 'is-empty']]];

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Max 10 groups per condition set');
		FormattingConditionSetInput::createFromInputArray(['groups' => array_fill(0, 11, $group)]);
	}

	public function testConditionSetCollectsUniqueColumnIdsAcrossGroups(): void {
		$set = FormattingConditionSetInput::createFromInputArray(['groups' => [
			['conditions' => [
				['columnId' => 1, 'columnType' => 'number', 'operator' => 'is-empty'],
				['columnId' => 2, 'columnType' => 'number', 'operator' => 'is-empty'],
			]],
			['conditions' => [
				['columnId' => 2, 'columnType' => 'number', 'operator' => 'is-empty'],
				['columnId' => 3, 'columnType' => 'number', 'operator' => 'is-empty'],
			]],
		]]);

		$this->assertSame([1, 2, 3], $set->collectColumnIds());
		$this->assertCount(2, $set->toArray()['groups']);
	}

	public function testRuleDefaultsEnabledToTrueAndSerializes(): void {
		$rule = FormattingRuleInput::createFromInputArray([
			'title' => 'Overdue',
			'condition' => $this->validConditionSet(),
			'format' => ['backgroundColor' => '#ff0000'],
		]);

		$this->assertTrue($rule->isEnabled());
		$this->assertSame('Overdue', $rule->getTitle());
		$this->assertNull($rule->getId(), 'a rule without an id is a new rule');
		$this->assertSame([
			'id' => null,
			'title' => 'Overdue',
			'enabled' => true,
			'condition' => $this->validConditionSet(),
			'format' => ['backgroundColor' => '#ff0000'],
		], $rule->toArray());
	}

	public function testRuleKeepsTheIdentityTheClientSentBack(): void {
		$rule = FormattingRuleInput::createFromInputArray([
			'id' => 'b1d0f1e2-0000-4000-8000-000000000000',
			'title' => 'Overdue',
			'condition' => $this->validConditionSet(),
			'format' => [],
		]);

		$this->assertSame('b1d0f1e2-0000-4000-8000-000000000000', $rule->getId());
	}

	public function testRuleRejectsAnOverlongId(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('id must be at most 36 characters');
		FormattingRuleInput::createFromInputArray([
			'id' => str_repeat('a', 37),
			'title' => 'x',
			'condition' => $this->validConditionSet(),
			'format' => [],
		]);
	}

	public function testRuleHonoursExplicitEnabledFlag(): void {
		$rule = FormattingRuleInput::createFromInputArray([
			'title' => 'Off',
			'enabled' => '0',
			'condition' => $this->validConditionSet(),
			'format' => [],
		]);

		$this->assertFalse($rule->isEnabled());
	}

	/** @return iterable<string, array{array<string, mixed>, string}> */
	public static function invalidRuleProvider(): iterable {
		$condition = ['groups' => [['conditions' => [['columnId' => 1, 'columnType' => 'number', 'operator' => 'is-empty']]]]];
		yield 'missing title' => [['condition' => $condition, 'format' => []], 'title is required'];
		yield 'missing condition' => [['title' => 'x', 'format' => []], 'condition must be an array'];
		yield 'condition not array' => [['title' => 'x', 'condition' => 'nope', 'format' => []], 'condition must be an array'];
		yield 'missing format' => [['title' => 'x', 'condition' => $condition], 'format must be an array'];
	}

	/** @dataProvider invalidRuleProvider */
	public function testRuleRejectsInvalidInput(array $input, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		FormattingRuleInput::createFromInputArray($input);
	}

	public function testRuleSetParsesRowTargetWithRules(): void {
		$ruleSet = FormattingRuleSetInput::createFromInputArray([
			'title' => 'Highlights',
			'targetType' => 'row',
			'targetCol' => null,
			'mode' => 'all-matches',
			'enabled' => false,
			'rules' => [[
				'title' => 'R1',
				'condition' => $this->validConditionSet(),
				'format' => ['fontWeight' => 'bold'],
			]],
		]);

		$this->assertSame('Highlights', $ruleSet->getTitle());
		$this->assertSame('row', $ruleSet->getTargetType());
		$this->assertNull($ruleSet->getTargetCol());
		$this->assertSame('all-matches', $ruleSet->getMode());
		$this->assertFalse($ruleSet->isEnabled());
		$this->assertCount(1, $ruleSet->getRules());
		$this->assertInstanceOf(FormattingRuleInput::class, $ruleSet->getRules()[0]);
		$this->assertSame('R1', $ruleSet->toArray()['rules'][0]['title']);
	}

	public function testRuleSetColumnTargetCastsTargetCol(): void {
		$ruleSet = FormattingRuleSetInput::createFromInputArray([
			'title' => 'Col',
			'targetType' => 'column',
			'targetCol' => '42',
			'mode' => 'first-match',
		]);

		$this->assertSame(42, $ruleSet->getTargetCol());
		$this->assertTrue($ruleSet->isEnabled(), 'enabled defaults to true');
		$this->assertSame([], $ruleSet->getRules(), 'rules default to an empty list');
	}

	/** @return iterable<string, array{array<string, mixed>, string}> */
	public static function invalidRuleSetProvider(): iterable {
		yield 'missing title' => [['targetType' => 'row', 'mode' => 'first-match'], 'title is required'];
		yield 'bad target type' => [['title' => 'x', 'targetType' => 'cell', 'mode' => 'first-match'], 'targetType must be "row" or "column"'];
		yield 'column target without column' => [['title' => 'x', 'targetType' => 'column', 'mode' => 'first-match'], 'targetCol is required when targetType is "column"'];
		yield 'bad mode' => [['title' => 'x', 'targetType' => 'row', 'mode' => 'last-match'], 'mode must be "first-match" or "all-matches"'];
		yield 'rule not array' => [['title' => 'x', 'targetType' => 'row', 'mode' => 'first-match', 'rules' => ['nope']], 'Each rule must be an array'];
	}

	/** @dataProvider invalidRuleSetProvider */
	public function testRuleSetRejectsInvalidInput(array $input, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		FormattingRuleSetInput::createFromInputArray($input);
	}

	private function validConditionSet(): array {
		return ['groups' => [['conditions' => [[
			'columnId' => 1,
			'columnType' => 'number',
			'operator' => 'is-greater-than',
			'value' => 5,
		]]]]];
	}
}
