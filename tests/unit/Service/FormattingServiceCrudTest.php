<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\FormattingRuleColMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Model\FormattingRuleInput;
use OCA\Tables\Model\FormattingRuleSetInput;
use OCA\Tables\Service\FormattingService;
use OCA\Tables\Service\PermissionsService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FormattingServiceCrudTest extends TestCase {
	private const VIEW_ID = 5;
	private const TABLE_ID = 3;
	private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	private View $view;
	private FormattingService $service;
	private $viewMapper;
	private $columnMapper;
	private $ruleColMapper;
	private $permissionsService;

	protected function setUp(): void {
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->columnMapper = $this->createMock(ColumnMapper::class);
		$this->ruleColMapper = $this->createMock(FormattingRuleColMapper::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);

		$this->service = new FormattingService(
			$this->permissionsService,
			$this->createMock(LoggerInterface::class),
			'user1',
			$this->viewMapper,
			$this->columnMapper,
			$this->ruleColMapper,
		);
	}

	public function testCreateRuleSetGeneratesIdsAssignsSortOrderAndPersists(): void {
		$this->allowManage();
		$view = $this->viewWith([$this->storedRuleSet('rs-existing')]);
		$this->columnsOfView([10, 11]);
		$persisted = $this->captureUpdate();

		$this->ruleColMapper->expects($this->once())->method('syncForRule')
			->with($this->matchesRegularExpression(self::UUID_PATTERN), self::VIEW_ID, [10]);

		$created = $this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'rules' => [$this->ruleData(columnId: 10)],
		]));

		$this->assertMatchesRegularExpression(self::UUID_PATTERN, $created['id']);
		$this->assertSame(1, $created['sortOrder'], 'appended after the existing rule set');
		$this->assertFalse($created['broken']);
		$this->assertCount(1, $created['rules']);
		$this->assertMatchesRegularExpression(self::UUID_PATTERN, $created['rules'][0]['id']);
		$this->assertSame(0, $created['rules'][0]['sortOrder']);
		$this->assertSame(['backgroundColor' => '#ff0000'], $created['rules'][0]['format']);

		$this->assertCount(2, $persisted());
		$this->assertSame($created['id'], $persisted()[1]['id']);
		$this->assertSame('rs-existing', $persisted()[0]['id']);
		$this->assertSame(self::VIEW_ID, $view->getId());
	}

	public function testCreateRuleSetThrowsPermissionErrorBeforeTouchingTheView(): void {
		$this->permissionsService->method('canManageViewById')->willReturn(false);
		$this->viewMapper->expects($this->never())->method('find');

		$this->expectException(PermissionError::class);
		$this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput());
	}

	public function testCreateRuleSetThrowsNotFoundWhenViewDoesNotExist(): void {
		$this->allowManage();
		$this->viewMapper->method('find')->willThrowException(new DoesNotExistException('nope'));

		$this->expectException(NotFoundError::class);
		$this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput());
	}

	public function testCreateRuleSetReportsThePositionTheCallerCanSee(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-hidden', [$this->storedRule('r-hidden', columnId: 99)])]);
		$this->columnsOfView([10]);
		$this->viewMapper->method('update')->willReturnArgument(0);

		$created = $this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'rules' => [$this->ruleData(columnId: 10)],
		]));

		$this->assertSame(0, $created['sortOrder'], 'the set is the first one this caller can see');
	}

	public function testCreateRuleSetReportsTheStoredPositionToSomeoneWhoSeesEverything(): void {
		$this->allowManage();
		$this->allowManageTable();
		$this->viewWith([$this->storedRuleSet('rs-hidden', [$this->storedRule('r-hidden', columnId: 99)])]);
		$this->columnsOfView([10]);
		$this->viewMapper->method('update')->willReturnArgument(0);

		$created = $this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'rules' => [$this->ruleData(columnId: 10)],
		]));

		$this->assertSame(1, $created['sortOrder']);
	}

	public function testUpdateRuleSetReportsRulePositionsWithoutGaps(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [
			$this->storedRule('r-hidden', columnId: 99, sortOrder: 0),
			$this->storedRule('r-visible', columnId: 10, sortOrder: 1),
		])]);
		$this->columnsOfView([10]);
		$this->viewMapper->method('update')->willReturnArgument(0);

		$updated = $this->service->updateRuleSet(self::VIEW_ID, 'rs-1', 'user1', $this->ruleSetInput([
			'rules' => [array_merge($this->ruleData(columnId: 10), ['id' => 'r-visible'])],
		]));

		$this->assertSame(['r-visible'], array_column($updated['rules'], 'id'));
		$this->assertSame([0], array_column($updated['rules'], 'sortOrder'));
	}

	public function testCreateRuleSetStoresTheMarker(): void {
		$this->allowManage();
		$this->viewWith([]);
		$this->columnsOfView([10]);
		$persisted = $this->captureUpdate();

		$created = $this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'marker' => 'star',
		]));

		$this->assertSame('star', $created['marker']);
		$this->assertSame('star', $persisted()[0]['marker']);
	}

	public function testUpdateRuleSetReplacesTheMarker(): void {
		$this->allowManage();
		$stored = $this->storedRuleSet('rs-1');
		$stored['marker'] = 'star';
		$this->viewWith([$stored]);
		$this->columnsOfView([10]);
		$persisted = $this->captureUpdate();

		$this->service->updateRuleSet(self::VIEW_ID, 'rs-1', 'user1', $this->ruleSetInput([
			'marker' => 'alert',
		]));

		$this->assertSame('alert', $persisted()[0]['marker']);
	}

	public function testCreateRuleSetRejectsAConditionColumnTheViewDoesNotExpose(): void {
		$this->allowManage();
		$this->viewWith([]);
		$this->columnsOfView([10]);
		$this->viewMapper->expects($this->never())->method('update');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Column 99 is not part of this view');
		$this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'rules' => [$this->ruleData(columnId: 99)],
		]));
	}

	public function testCreateRuleSetRejectsAColumnTypeTheColumnDoesNotHave(): void {
		$this->allowManage();
		$this->viewWith([]);
		$this->columnsOfView([10], stubTypes: false);
		$this->columnMapper->method('findAllByTable')->willReturn([$this->column(10, 'text', 'line')]);
		$this->viewMapper->expects($this->never())->method('update');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Column 10 is of type text-line, not number');
		$this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'rules' => [$this->ruleData(columnId: 10)],
		]));
	}

	public function testCreateRuleSetIndexesATargetColumnEvenWithNoRules(): void {
		$this->allowManage();
		$this->viewWith([]);
		$this->columnsOfView([10, 20]);
		$this->viewMapper->method('update')->willReturnArgument(0);

		$indexed = [];
		$this->ruleColMapper->method('syncForRule')
			->willReturnCallback(function (string $ruleId, int $viewId, array $columnIds) use (&$indexed): void {
				$indexed[] = $columnIds;
			});

		$created = $this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'targetType' => 'column',
			'targetCol' => 20,
			'rules' => [],
		]));

		$this->assertSame([[20]], $indexed, 'the set itself carries the target column');
		$this->assertNotEmpty($created['id']);
	}

	public function testAFailedIndexWriteIsReportedRatherThanSwallowed(): void {
		$this->allowManage();
		$this->viewWith([]);
		$this->columnsOfView([10]);
		$this->viewMapper->method('update')->willReturnArgument(0);
		$this->ruleColMapper->method('syncForRule')->willThrowException(new \OCP\DB\Exception());

		$this->expectException(InternalError::class);
		$this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'rules' => [$this->ruleData(columnId: 10)],
		]));
	}

	public function testCreateRuleSetRejectsATargetColumnTheViewDoesNotExpose(): void {
		$this->allowManage();
		$this->viewWith([]);
		$this->columnsOfView([10]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Target column 77 is not part of this view');
		$this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'targetType' => 'column',
			'targetCol' => 77,
		]));
	}

	public function testCreateRuleSetRejectsATableColumnTheViewHides(): void {
		$this->allowManage();
		$view = $this->viewWith([]);
		$view->setColumnsArray([10]);
		$this->columnMapper->method('findAllIdsByTable')->with(self::TABLE_ID)->willReturn([10, 42]);
		$this->viewMapper->expects($this->never())->method('update');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Column 42 is not part of this view');
		$this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'rules' => [$this->ruleData(columnId: 42)],
		]));
	}

	public function testCreateRuleSetIndexesTheTargetColumnSoItsDeletionIsNoticed(): void {
		$this->allowManage();
		$this->viewWith([]);
		$this->columnsOfView([10, 20]);
		$this->viewMapper->method('update')->willReturnArgument(0);

		$this->ruleColMapper->expects($this->once())->method('syncForRule')
			->with($this->anything(), self::VIEW_ID, [10, 20]);

		$this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput([
			'targetType' => 'column',
			'targetCol' => 20,
			'rules' => [$this->ruleInput(columnId: 10)->toArray()],
		]));
	}

	public function testCreateRuleSetEnforcesFiftyRuleSetLimit(): void {
		$this->allowManage();
		$existing = array_map(fn (int $i) => $this->storedRuleSet('rs-' . $i), range(1, 50));
		$this->viewWith($existing);
		$this->columnsOfView([10]);
		$this->viewMapper->expects($this->never())->method('update');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Maximum of 50 rule sets per view exceeded');
		$this->service->createRuleSet(self::VIEW_ID, 'user1', $this->ruleSetInput());
	}

	public function testUpdateRuleSetAppliesTheSubmittedRulesAndRebuildsJunction(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [$this->storedRule('old-rule', columnId: 10)])]);
		$this->columnsOfView([10, 11]);
		$persisted = $this->captureUpdate();

		$this->ruleColMapper->expects($this->never())->method('deleteByRule');
		$this->ruleColMapper->expects($this->exactly(2))->method('syncForRule');

		$updated = $this->service->updateRuleSet(self::VIEW_ID, 'rs-1', 'user1', $this->ruleSetInput([
			'title' => 'Renamed',
			'mode' => 'all-matches',
			'enabled' => false,
			'rules' => [$this->ruleData(columnId: 11)],
		]));

		$this->assertSame('rs-1', $updated['id'], 'the rule set keeps its id');
		$this->assertSame('Renamed', $updated['title']);
		$this->assertSame('all-matches', $updated['mode']);
		$this->assertFalse($updated['enabled']);
		$this->assertCount(1, $updated['rules'], 'the response reports only what was submitted');
		$this->assertNotSame('old-rule', $updated['rules'][0]['id']);
		$this->assertSame('Renamed', $persisted()[0]['title']);
	}

	public function testUpdateRuleSetKeepsAStoredRuleTheRequestDoesNotMention(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [
			$this->storedRule('visible-rule', columnId: 10, sortOrder: 0),
			$this->storedRule('withheld-rule', columnId: 11, sortOrder: 1),
		])]);
		$this->columnsOfView([10, 11]);
		$persisted = $this->captureUpdate();

		$this->ruleColMapper->expects($this->never())->method('deleteByRule');

		$updated = $this->service->updateRuleSet(self::VIEW_ID, 'rs-1', 'user1', $this->ruleSetInput([
			'enabled' => false,
			'rules' => [array_merge($this->ruleData(columnId: 10), ['id' => 'visible-rule'])],
		]));

		$this->assertSame(
			['visible-rule', 'withheld-rule'],
			array_column($persisted()[0]['rules'], 'id'),
			'the rule the request never saw is still stored',
		);
		$this->assertSame(
			['visible-rule'],
			array_column($updated['rules'], 'id'),
			'and is not handed back to a caller it was withheld from',
		);
	}

	public function testUpdateRuleSetPutsAPreservedRuleBackWhereItWas(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [
			$this->storedRule('withheld-rule', columnId: 11, sortOrder: 0),
			$this->storedRule('visible-rule', columnId: 10, sortOrder: 1),
		])]);
		$this->columnsOfView([10, 11]);
		$persisted = $this->captureUpdate();

		$this->service->updateRuleSet(self::VIEW_ID, 'rs-1', 'user1', $this->ruleSetInput([
			'rules' => [array_merge($this->ruleData(columnId: 10), ['id' => 'visible-rule'])],
		]));

		$this->assertSame(
			['withheld-rule', 'visible-rule'],
			array_column($persisted()[0]['rules'], 'id'),
			'the withheld rule still wins over the one the caller sent back',
		);
		$this->assertSame([0, 1], array_column($persisted()[0]['rules'], 'sortOrder'));
	}

	public function testUpdateRuleSetRejectsTheSameStoredRuleIdTwice(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [$this->storedRule('rule-1', columnId: 10)])]);
		$this->columnsOfView([10]);
		$this->viewMapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->service->updateRuleSet(self::VIEW_ID, 'rs-1', 'user1', $this->ruleSetInput([
			'rules' => [
				array_merge($this->ruleData(columnId: 10), ['id' => 'rule-1']),
				array_merge($this->ruleData(columnId: 10), ['id' => 'rule-1']),
			],
		]));
	}

	public function testUpdateRuleSetKeepsIdentityOfRulesTheClientSentBack(): void {
		$this->allowManage();
		$storedRule = $this->storedRule('rule-stable', columnId: 10);
		$storedRule['broken'] = true;
		$storedRule['enabled'] = false;
		$this->viewWith([$this->storedRuleSet('rs-1', [$storedRule])]);
		$this->columnsOfView([10], stubTypes: false);
		$this->columnMapper->method('findAllByTable')->willReturn([]);
		$persisted = $this->captureUpdate();

		$this->ruleColMapper->expects($this->never())->method('deleteByRule');

		$this->service->updateRuleSet(self::VIEW_ID, 'rs-1', 'user1', $this->ruleSetInput([
			'enabled' => false,
			'rules' => [array_merge($this->ruleData(columnId: 10), ['id' => 'rule-stable'])],
		]));

		$this->assertSame('rule-stable', $persisted()[0]['rules'][0]['id'], 'the rule keeps its id');
		$this->assertTrue($persisted()[0]['rules'][0]['broken'], 'the rule keeps its broken state');
	}

	public function testUpdateRuleSetIgnoresARuleIdThatIsNotStored(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [])]);
		$this->columnsOfView([10]);
		$persisted = $this->captureUpdate();

		$this->service->updateRuleSet(self::VIEW_ID, 'rs-1', 'user1', $this->ruleSetInput([
			'rules' => [array_merge($this->ruleData(columnId: 10), ['id' => 'not-stored'])],
		]));

		$this->assertMatchesRegularExpression(
			self::UUID_PATTERN,
			$persisted()[0]['rules'][0]['id'],
			'a client cannot choose the id of a new rule',
		);
	}

	public function testUpdateRuleSetThrowsNotFoundForUnknownId(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1')]);
		$this->viewMapper->expects($this->never())->method('update');

		$this->expectException(NotFoundError::class);
		$this->expectExceptionMessage('Rule set not found: rs-404');
		$this->service->updateRuleSet(self::VIEW_ID, 'rs-404', 'user1', $this->ruleSetInput());
	}

	public function testDeleteRuleSetRemovesJunctionRowsAndRenumbersSortOrder(): void {
		$this->allowManage();
		$this->viewWith([
			$this->storedRuleSet('rs-1', [$this->storedRule('rule-a')], sortOrder: 0),
			$this->storedRuleSet('rs-2', [], sortOrder: 1),
			$this->storedRuleSet('rs-3', [], sortOrder: 2),
		]);
		$persisted = $this->captureUpdate();

		$this->ruleColMapper->expects($this->once())->method('deleteByRule')->with('rule-a');

		$this->service->deleteRuleSet(self::VIEW_ID, 'rs-1', 'user1');

		$this->assertSame(['rs-2', 'rs-3'], array_column($persisted(), 'id'));
		$this->assertSame([0, 1], array_column($persisted(), 'sortOrder'));
	}

	public function testDeleteRuleSetThrowsNotFoundForUnknownId(): void {
		$this->allowManage();
		$this->viewWith([]);

		$this->expectException(NotFoundError::class);
		$this->service->deleteRuleSet(self::VIEW_ID, 'rs-404', 'user1');
	}

	public function testReorderRuleSetsAppliesOrderAndRestoresOmittedSetsAtTheirPosition(): void {
		$this->allowManage();
		$this->viewWith([
			$this->storedRuleSet('rs-1', sortOrder: 0),
			$this->storedRuleSet('rs-2', sortOrder: 1),
			$this->storedRuleSet('rs-3', sortOrder: 2),
		]);
		$persisted = $this->captureUpdate();

		$this->service->reorderRuleSets(self::VIEW_ID, 'user1', ['rs-3', 'rs-1']);

		$this->assertSame(['rs-3', 'rs-2', 'rs-1'], array_column($persisted(), 'id'));
		$this->assertSame([0, 1, 2], array_column($persisted(), 'sortOrder'));
	}

	public function testReorderRuleSetsThrowsNotFoundForUnknownId(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1')]);
		$this->viewMapper->expects($this->never())->method('update');

		$this->expectException(NotFoundError::class);
		$this->expectExceptionMessage('Rule set not found: rs-404');
		$this->service->reorderRuleSets(self::VIEW_ID, 'user1', ['rs-404']);
	}

	public function testCreateRuleAppendsToRuleSetWithNextSortOrder(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [$this->storedRule('rule-a')])]);
		$this->columnsOfView([10]);
		$persisted = $this->captureUpdate();

		$this->ruleColMapper->expects($this->once())->method('syncForRule')
			->with($this->matchesRegularExpression(self::UUID_PATTERN), self::VIEW_ID, [10]);

		$rule = $this->service->createRule(self::VIEW_ID, 'rs-1', 'user1', $this->ruleInput(columnId: 10));

		$this->assertMatchesRegularExpression(self::UUID_PATTERN, $rule['id']);
		$this->assertSame(1, $rule['sortOrder']);
		$this->assertTrue($rule['enabled']);
		$this->assertFalse($rule['broken']);
		$this->assertSame(['rule-a', $rule['id']], array_column($persisted()[0]['rules'], 'id'));
	}

	public function testCreateRuleThrowsNotFoundForUnknownRuleSet(): void {
		$this->allowManage();
		$this->viewWith([]);

		$this->expectException(NotFoundError::class);
		$this->service->createRule(self::VIEW_ID, 'rs-404', 'user1', $this->ruleInput());
	}

	public function testCreateRuleEnforcesTwentyRulesPerSetLimit(): void {
		$this->allowManage();
		$rules = array_map(fn (int $i) => $this->storedRule('rule-' . $i), range(1, 20));
		$this->viewWith([$this->storedRuleSet('rs-1', $rules)]);
		$this->columnsOfView([10]);
		$this->viewMapper->expects($this->never())->method('update');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Maximum of 20 rules per rule set exceeded');
		$this->service->createRule(self::VIEW_ID, 'rs-1', 'user1', $this->ruleInput(columnId: 10));
	}

	public function testUpdateRuleReplacesFieldsAndKeepsId(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [$this->storedRule('rule-a', columnId: 10)])]);
		$this->columnsOfView([10, 11]);
		$persisted = $this->captureUpdate();

		$this->ruleColMapper->expects($this->once())->method('syncForRule')->with('rule-a', self::VIEW_ID, [11]);

		$updated = $this->service->updateRule(self::VIEW_ID, 'rs-1', 'rule-a', 'user1', $this->ruleInput(
			columnId: 11,
			title: 'Changed',
			enabled: false,
			format: ['fontWeight' => 'bold'],
		));

		$this->assertSame('rule-a', $updated['id']);
		$this->assertSame('Changed', $updated['title']);
		$this->assertFalse($updated['enabled']);
		$this->assertSame(['fontWeight' => 'bold'], $updated['format']);
		$this->assertSame(11, $updated['condition']['groups'][0]['conditions'][0]['columnId']);
		$this->assertSame('Changed', $persisted()[0]['rules'][0]['title']);
	}

	public function testUpdateRuleHealsBrokenRuleWhenColumnTypesMatchAgain(): void {
		$this->allowManage();
		$broken = $this->storedRule('rule-a', columnId: 10);
		$broken['broken'] = true;
		$broken['enabled'] = false;
		$this->viewWith([$this->storedRuleSet('rs-1', [$broken])]);
		$this->columnsOfView([10], stubTypes: false);
		$this->columnMapper->method('findAllByTable')->with(self::TABLE_ID)->willReturn([$this->column(10, 'number')]);

		$persistedStates = [];
		$this->viewMapper->method('update')
			->with($this->callback(function (View $v) use (&$persistedStates): bool {
				$persistedStates[] = json_decode($v->getFormatting(), true);
				return true;
			}))
			->willReturnArgument(0);

		$returned = $this->service->updateRule(self::VIEW_ID, 'rs-1', 'rule-a', 'user1', $this->ruleInput(columnId: 10));

		$final = end($persistedStates);
		$this->assertFalse($final[0]['rules'][0]['broken']);
		$this->assertTrue($final[0]['rules'][0]['enabled'], 'the input asked for the rule to be enabled');
		$this->assertFalse($returned['broken'], 'the response has to show the healed rule, not the stale one');
		$this->assertTrue($returned['enabled']);
	}

	public function testHealingDoesNotReEnableARuleTheUserTurnedOff(): void {
		$this->allowManage();
		$broken = $this->storedRule('rule-a', columnId: 10);
		$broken['broken'] = true;
		$broken['enabled'] = false;
		$this->viewWith([$this->storedRuleSet('rs-1', [$broken])]);
		$this->columnsOfView([10], stubTypes: false);
		$this->columnMapper->method('findAllByTable')->with(self::TABLE_ID)->willReturn([$this->column(10, 'number')]);

		$persistedStates = [];
		$this->viewMapper->method('update')
			->with($this->callback(function (View $v) use (&$persistedStates): bool {
				$persistedStates[] = json_decode($v->getFormatting(), true);
				return true;
			}))
			->willReturnArgument(0);

		$returned = $this->service->updateRule(self::VIEW_ID, 'rs-1', 'rule-a', 'user1', $this->ruleInput(columnId: 10, enabled: false));

		$final = end($persistedStates);
		$this->assertFalse($final[0]['rules'][0]['broken'], 'the condition is valid again, so the rule heals');
		$this->assertFalse($final[0]['rules'][0]['enabled'], 'healing must not override the switch the user left off');
		$this->assertFalse($returned['enabled']);
	}

	public function testUpdateRuleLeavesBrokenRuleBrokenWhenColumnTypeStillDiffers(): void {
		$this->allowManage();
		$broken = $this->storedRule('rule-a', columnId: 10);
		$broken['broken'] = true;
		$broken['enabled'] = false;
		$this->viewWith([$this->storedRuleSet('rs-1', [$broken])]);
		$this->columnsOfView([10], stubTypes: false);
		$this->columnMapper->method('findAllByTable')->willReturn([$this->column(10, 'text', 'line')]);

		$persistedStates = [];
		$this->viewMapper->method('update')
			->with($this->callback(function (View $v) use (&$persistedStates): bool {
				$persistedStates[] = json_decode($v->getFormatting(), true);
				return true;
			}))
			->willReturnArgument(0);

		$this->service->updateRule(self::VIEW_ID, 'rs-1', 'rule-a', 'user1', $this->ruleInput(columnId: 10, enabled: false));

		$final = end($persistedStates);
		$this->assertTrue($final[0]['rules'][0]['broken'], 'a type mismatch keeps the rule broken');
		$this->assertFalse($final[0]['rules'][0]['enabled']);
	}

	public function testUpdateRuleThrowsNotFoundForUnknownRule(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [$this->storedRule('rule-a')])]);

		$this->expectException(NotFoundError::class);
		$this->expectExceptionMessage('Rule not found: rule-404');
		$this->service->updateRule(self::VIEW_ID, 'rs-1', 'rule-404', 'user1', $this->ruleInput());
	}

	public function testDeleteRuleRemovesRuleAndJunctionRows(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [$this->storedRule('rule-a'), $this->storedRule('rule-b')])]);
		$persisted = $this->captureUpdate();

		$this->ruleColMapper->expects($this->once())->method('deleteByRule')->with('rule-a');

		$this->service->deleteRule(self::VIEW_ID, 'rs-1', 'rule-a', 'user1');

		$this->assertSame(['rule-b'], array_column($persisted()[0]['rules'], 'id'));
	}

	public function testDeleteRuleRenumbersTheSurvivingRules(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1', [
			$this->storedRule('rule-a', sortOrder: 0),
			$this->storedRule('rule-b', sortOrder: 1),
			$this->storedRule('rule-c', sortOrder: 2),
		])]);
		$persisted = $this->captureUpdate();

		$this->service->deleteRule(self::VIEW_ID, 'rs-1', 'rule-a', 'user1');

		$this->assertSame([0, 1], array_column($persisted()[0]['rules'], 'sortOrder'));
	}

	public function testUpdateRuleAcceptsABrokenRuleSentBackUnchanged(): void {
		$this->allowManage();
		$broken = $this->storedRule('rule-a', columnId: 99);
		$broken['broken'] = true;
		$this->viewWith([$this->storedRuleSet('rs-1', [$broken])]);
		$this->columnsOfView([10]);
		$persisted = $this->captureUpdate();

		$updated = $this->service->updateRule(self::VIEW_ID, 'rs-1', 'rule-a', 'user1', $this->ruleInput(columnId: 99));

		$this->assertSame('rule-a', $updated['id']);
		$this->assertSame('rule-a', $persisted()[0]['rules'][0]['id']);
	}

	public function testDeleteRuleThrowsNotFoundForUnknownRule(): void {
		$this->allowManage();
		$this->viewWith([$this->storedRuleSet('rs-1')]);
		$this->ruleColMapper->expects($this->never())->method('deleteByRule');

		$this->expectException(NotFoundError::class);
		$this->service->deleteRule(self::VIEW_ID, 'rs-1', 'rule-404', 'user1');
	}

	private function allowManage(): void {
		$this->permissionsService->method('canManageViewById')->with(self::VIEW_ID, 'user1')->willReturn(true);
	}

	private function allowManageTable(): void {
		$this->permissionsService->method('canManageTableById')->with(self::TABLE_ID, 'user1')->willReturn(true);
	}

	private function viewWith(array $formatting): View {
		$view = new View();
		$view->setId(self::VIEW_ID);
		$view->setTableId(self::TABLE_ID);
		$view->setFormatting(json_encode($formatting));
		$this->viewMapper->method('find')->with(self::VIEW_ID)->willReturn($view);
		$this->view = $view;
		return $view;
	}

	/** @param int[] $ids */
	private function columnsOfView(array $ids, string $type = 'number', bool $stubTypes = true): void {
		$this->view->setColumnsArray($ids);
		$this->columnMapper->method('findAllIdsByTable')->with(self::TABLE_ID)->willReturn($ids);
		if (!$stubTypes) {
			return;
		}
		$this->columnMapper->method('findAllByTable')->with(self::TABLE_ID)->willReturn(
			array_map(static function (int $id) use ($type): Column {
				$column = new Column();
				$column->setId($id);
				$column->setType($type);
				return $column;
			}, $ids),
		);
	}

	/**
	 * @return callable(): array
	 */
	private function captureUpdate(): callable {
		$captured = null;
		$this->viewMapper->expects($this->once())->method('update')
			->with($this->callback(function (View $v) use (&$captured): bool {
				$captured = json_decode($v->getFormatting(), true);
				return true;
			}))
			->willReturnArgument(0);
		return static function () use (&$captured): array {
			return $captured ?? [];
		};
	}

	private function column(int $id, string $type, ?string $subtype = null): Column {
		$column = new Column();
		$column->setId($id);
		$column->setType($type);
		$column->setSubtype($subtype);
		return $column;
	}

	private function ruleData(int $columnId = 10, string $title = 'Rule', bool $enabled = true, array $format = ['backgroundColor' => '#ff0000']): array {
		return [
			'title' => $title,
			'enabled' => $enabled,
			'condition' => ['groups' => [['conditions' => [[
				'columnId' => $columnId,
				'columnType' => 'number',
				'operator' => 'is-greater-than',
				'value' => 5,
			]]]]],
			'format' => $format,
		];
	}

	private function ruleInput(int $columnId = 10, string $title = 'Rule', bool $enabled = true, array $format = ['backgroundColor' => '#ff0000']): FormattingRuleInput {
		return FormattingRuleInput::createFromInputArray($this->ruleData($columnId, $title, $enabled, $format));
	}

	private function ruleSetInput(array $overrides = []): FormattingRuleSetInput {
		return FormattingRuleSetInput::createFromInputArray(array_merge([
			'title' => 'Highlights',
			'targetType' => 'row',
			'targetCol' => null,
			'mode' => 'first-match',
			'enabled' => true,
			'rules' => [],
		], $overrides));
	}

	private function storedRule(string $id, int $columnId = 10, int $sortOrder = 0): array {
		return [
			'id' => $id,
			'title' => 'Rule ' . $id,
			'sortOrder' => $sortOrder,
			'enabled' => true,
			'broken' => false,
			'condition' => ['groups' => [['conditions' => [[
				'columnId' => $columnId,
				'columnType' => 'number',
				'operator' => 'is-greater-than',
				'value' => 5,
			]]]]],
			'format' => ['backgroundColor' => '#ff0000'],
		];
	}

	private function storedRuleSet(string $id, array $rules = [], int $sortOrder = 0): array {
		return [
			'id' => $id,
			'title' => 'RS ' . $id,
			'targetType' => 'row',
			'targetCol' => null,
			'mode' => 'first-match',
			'sortOrder' => $sortOrder,
			'enabled' => true,
			'broken' => false,
			'rules' => $rules,
		];
	}
}
