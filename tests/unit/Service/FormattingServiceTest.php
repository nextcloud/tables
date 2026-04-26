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

	// ── handleColumnDeletion ──────────────────────────────────────────────────

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
		$this->assertFalse($capturedFormatting[0]['rules'][0]['enabled']);
		// rule-2 (column 20) must be unaffected
		$this->assertFalse($capturedFormatting[0]['rules'][1]['broken']);
	}

	public function testHandleColumnDeletionDoesNothingWhenNoRulesAffected(): void {
		$this->ruleColMapper->method('findRuleIdsByColumn')->with(10)->willReturn([]);
		$this->viewMapper->expects($this->never())->method('find');
		$this->viewMapper->expects($this->never())->method('update');

		$this->service->handleColumnDeletion(10);
	}

	// ── handleColumnTypeChange ────────────────────────────────────────────────

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
		$this->assertFalse($capturedFormatting[0]['rules'][0]['enabled']);
	}

	// ── handleSelectionOptionDeletion ─────────────────────────────────────────

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

	// ── saveForView (used by import) ──────────────────────────────────────────

	public function testSaveForViewPersistsFormattingAndRebuildsJunctionIndex(): void {
		$view = new View();
		$view->setId(5);
		$view->setFormatting('[]');

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
				'condition' => ['groups' => [['conditions' => [['columnId' => 10, 'columnType' => 'number', 'operator' => 'gt', 'value' => 5]]]]],
				'format' => ['backgroundColor' => '#ff0000'],
			]],
		]];

		$this->viewMapper->method('find')->with(5)->willReturn($view);
		$this->viewMapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->ruleColMapper->expects($this->once())->method('deleteByView')->with(5);
		$this->ruleColMapper->expects($this->once())->method('syncForRule')
			->with('rule-1', 5, [10]);

		$this->service->saveForView(5, $formatting);
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

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
				'operator' => 'eq',
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
