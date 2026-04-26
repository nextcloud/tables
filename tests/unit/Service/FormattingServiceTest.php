<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\FormattingRuleColMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\FormattingService;
use OCA\Tables\Service\PermissionsService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FormattingServiceTest extends TestCase {
	private FormattingService $service;
	private $viewMapper;
	private $columnMapper;
	private $ruleColMapper;
	private $permissionsService;
	private $logger;

	protected function setUp(): void {
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->columnMapper = $this->createMock(ColumnMapper::class);
		$this->ruleColMapper = $this->createMock(FormattingRuleColMapper::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new FormattingService(
			$this->permissionsService,
			$this->logger,
			'user1',
			$this->viewMapper,
			$this->columnMapper,
			$this->ruleColMapper,
		);
	}

	public function testHandleColumnDeletionMarksBrokenWhenAffectedRulesExist(): void {
		$rule1 = $this->makeRule('rule-1', columnId: 10);
		$rule2 = $this->makeRule('rule-2', columnId: 20);
		$formatting = [$this->makeRuleSet('rs-1', [$rule1, $rule2])];
		$view = $this->makeViewWithFormatting(5, $formatting);

		$this->ruleColMapper->method('findRuleIdsByColumn')
			->with(10)
			->willReturn([['rule_id' => 'rule-1', 'view_id' => 5]]);

		$this->viewMapper->method('find')->with(5)->willReturn($view);

		$capturedFormatting = null;
		$this->viewMapper->expects($this->once())->method('update')
			->with($this->callback(function (View $v) use (&$capturedFormatting): bool {
				$capturedFormatting = json_decode($v->getFormatting(), true);
				return true;
			}))
			->willReturnArgument(0);

		$this->ruleColMapper->expects($this->once())->method('deleteByColumn')->with(10);

		$this->service->handleColumnDeletion(10);

		$this->assertTrue($capturedFormatting[0]['rules'][0]['broken']);
		$this->assertTrue(
			$capturedFormatting[0]['rules'][0]['enabled'],
			'breaking a rule must not touch the user\'s enabled switch; broken already stops it evaluating',
		);
		// rule-2 (column 20) must be unaffected
		$this->assertFalse($capturedFormatting[0]['rules'][1]['broken']);
	}

	public function testHandleColumnDeletionDoesNothingWhenNoRulesAffected(): void {
		$this->ruleColMapper->method('findRuleIdsByColumn')->with(10)->willReturn([]);
		$this->viewMapper->expects($this->never())->method('find');
		$this->viewMapper->expects($this->never())->method('update');

		$this->service->handleColumnDeletion(10);
	}

	public function testHandleColumnTypeChangeMarksBrokenWhenAffectedRulesExist(): void {
		$rule = $this->makeRule('rule-1', columnId: 10, columnType: 'number');
		$formatting = [$this->makeRuleSet('rs-1', [$rule])];
		$view = $this->makeViewWithFormatting(5, $formatting);

		$this->ruleColMapper->method('findRuleIdsByColumn')
			->with(10)
			->willReturn([['rule_id' => 'rule-1', 'view_id' => 5]]);

		$this->viewMapper->method('find')->with(5)->willReturn($view);

		$capturedFormatting = null;
		$this->viewMapper->expects($this->once())->method('update')
			->with($this->callback(function (View $v) use (&$capturedFormatting): bool {
				$capturedFormatting = json_decode($v->getFormatting(), true);
				return true;
			}))
			->willReturnArgument(0);

		$this->service->handleColumnTypeChange(10, 'text-line');

		$this->assertTrue($capturedFormatting[0]['rules'][0]['broken']);
		$this->assertTrue(
			$capturedFormatting[0]['rules'][0]['enabled'],
			'breaking a rule must not touch the user\'s enabled switch; broken already stops it evaluating',
		);
	}

	public function testHandleSelectionOptionDeletionMarksBrokenWhenMagicValueUsed(): void {
		$rule = $this->makeRule('rule-1', columnId: 10, value: '@selection-id-7');
		$formatting = [$this->makeRuleSet('rs-1', [$rule])];
		$view = $this->makeViewWithFormatting(5, $formatting);

		$this->ruleColMapper->method('findRuleIdsByColumn')
			->with(10)
			->willReturn([['rule_id' => 'rule-1', 'view_id' => 5]]);

		$this->viewMapper->method('find')->with(5)->willReturn($view);

		$capturedFormatting = null;
		$this->viewMapper->expects($this->once())->method('update')
			->with($this->callback(function (View $v) use (&$capturedFormatting): bool {
				$capturedFormatting = json_decode($v->getFormatting(), true);
				return true;
			}))
			->willReturnArgument(0);

		$this->service->handleSelectionOptionDeletion(10, 7);

		$this->assertTrue($capturedFormatting[0]['rules'][0]['broken']);
	}

	public function testHandleSelectionOptionDeletionDoesNotMarkBrokenWhenMagicNotUsed(): void {
		// Rule references option 7, but handler fires for option 99 deletion
		$rule = $this->makeRule('rule-1', columnId: 10, value: '@selection-id-7');
		$formatting = [$this->makeRuleSet('rs-1', [$rule])];
		$view = $this->makeViewWithFormatting(5, $formatting);

		$this->ruleColMapper->method('findRuleIdsByColumn')
			->with(10)
			->willReturn([['rule_id' => 'rule-1', 'view_id' => 5]]);

		$this->viewMapper->method('find')->with(5)->willReturn($view);
		$this->viewMapper->expects($this->never())->method('update');

		$this->service->handleSelectionOptionDeletion(10, 99);
	}

	public function testSaveForViewPersistsFormattingAndRebuildsJunctionIndex(): void {
		$view = new View();
		$view->setId(5);
		$view->setTableId(7);
		$view->setFormatting('[]');
		$this->columnMapper->method('findAllIdsByTable')->with(7)->willReturn([10]);

		$formatting = [[
			'id' => 'rs-1',
			'title' => 'RS',
			'targetType' => 'row',
			'targetCol' => null,
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
				'condition' => ['groups' => [['conditions' => [['columnId' => 10, 'columnType' => 'number', 'operator' => 'is-greater-than', 'value' => 5]]]]],
				'format' => ['backgroundColor' => '#ff0000'],
			]],
		]];

		$this->viewMapper->method('find')->with(5)->willReturn($view);
		$persisted = null;
		$this->viewMapper->expects($this->once())->method('update')
			->with($this->callback(function (View $v) use (&$persisted): bool {
				$persisted = json_decode($v->getFormatting(), true);
				return true;
			}))
			->willReturnArgument(0);
		$this->ruleColMapper->expects($this->once())->method('deleteByView')->with(5);

		$indexedRuleId = null;
		$this->ruleColMapper->expects($this->once())->method('syncForRule')
			->with($this->callback(function (string $ruleId) use (&$indexedRuleId): bool {
				$indexedRuleId = $ruleId;
				return true;
			}), 5, [10]);

		$this->service->saveForView(5, $formatting);

		// Ids are reissued rather than taken from the payload: a rule id addresses rows in a
		// junction table shared by every view, so an import must not be able to name one.
		$this->assertNotSame('rule-1', $persisted[0]['rules'][0]['id']);
		$this->assertNotSame('rs-1', $persisted[0]['id']);
		$this->assertSame($persisted[0]['rules'][0]['id'], $indexedRuleId);
	}

	public function testSaveForViewRejectsAColumnOutsideTheViewsTable(): void {
		$view = new View();
		$view->setId(5);
		$view->setTableId(7);
		$view->setFormatting('[]');
		$this->viewMapper->method('find')->with(5)->willReturn($view);
		// The table owns column 10; the import references 99, which belongs elsewhere.
		$this->columnMapper->method('findAllIdsByTable')->with(7)->willReturn([10]);
		$this->viewMapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->service->saveForView(5, [[
			'id' => 'rs-1',
			'title' => 'RS',
			'targetType' => 'row',
			'targetCol' => null,
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
				'condition' => ['groups' => [['conditions' => [['columnId' => 99, 'columnType' => 'number', 'operator' => 'is-greater-than', 'value' => 5]]]]],
				'format' => ['backgroundColor' => '#ff0000'],
			]],
		]]);
	}

	private function makeRule(string $id, int $columnId = 1, string $columnType = 'number', ?string $value = null): array {
		return [
			'id' => $id,
			'title' => 'Rule ' . $id,
			'sortOrder' => 0,
			'enabled' => true,
			'broken' => false,
			'condition' => ['groups' => [['conditions' => [[
				'columnId' => $columnId,
				'columnType' => $columnType,
				'operator' => 'is-equal',
				'value' => $value ?? 1,
			]]]]],
			'format' => ['backgroundColor' => '#ffffff'],
		];
	}

	private function makeRuleSet(string $id, array $rules): array {
		return [
			'id' => $id,
			'title' => 'RS ' . $id,
			'targetType' => 'row',
			'targetCol' => null,
			'mode' => 'first-match',
			'sortOrder' => 0,
			'enabled' => true,
			'broken' => false,
			'rules' => $rules,
		];
	}

	private function makeViewWithFormatting(int $id, array $formatting): View {
		$view = new View();
		$view->setId($id);
		$view->setFormatting(json_encode($formatting));
		return $view;
	}
}
