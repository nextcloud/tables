<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use InvalidArgumentException;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\FormattingRuleColMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Model\FormattingRuleInput;
use OCA\Tables\Model\FormattingRuleSetInput;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Log\LoggerInterface;

class FormattingService extends SuperService {
	private const MAX_RULE_SETS_PER_VIEW = 50;
	private const MAX_RULES_PER_RULE_SET = 20;

	/** The formatting column is a TEXT column, which holds 65535 bytes on MySQL. */
	private const MAX_FORMATTING_BYTES = 65535;

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		private readonly ViewMapper $viewMapper,
		private readonly ColumnMapper $columnMapper,
		private readonly FormattingRuleColMapper $ruleColMapper,
	) {
		parent::__construct($logger, $userId, $permissionsService);
	}

	/**
	 * Persist a formatting array for a view and rebuild the junction index.
	 * Used by import to restore formatting without going through CRUD methods.
	 *
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws InvalidArgumentException
	 */
	public function saveForView(int $viewId, array $formatting): void {
		$view = $this->loadView($viewId);
		$this->validateViewLimits($formatting);
		$this->persistFormatting($view, $formatting);

		$this->ruleColMapper->deleteByView($viewId);
		foreach ($formatting as $ruleSet) {
			foreach ($ruleSet['rules'] ?? [] as $rule) {
				$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition'] ?? []);
			}
		}
	}

	/**
	 * @return array the created rule set (including generated id and sortOrder)
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 * @throws InvalidArgumentException
	 */
	public function createRuleSet(int $viewId, string $userId, FormattingRuleSetInput $input): array {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		$this->checkColumnOwnership($view->getTableId(), $input->getTargetCol(), $input->getRules());

		$ruleSet = [
			'id' => $this->generateUuid(),
			'title' => $input->getTitle(),
			'targetType' => $input->getTargetType(),
			'targetCol' => $input->getTargetCol(),
			'mode' => $input->getMode(),
			'sortOrder' => count($formatting),
			'enabled' => $input->isEnabled(),
			'broken' => false,
			'rules' => [],
		];
		foreach ($input->getRules() as $sortOrder => $ruleInput) {
			$ruleSet['rules'][] = $this->buildRuleData($ruleInput, $sortOrder);
		}

		$formatting[] = $ruleSet;
		$this->validateViewLimits($formatting);
		$this->persistFormatting($view, $formatting);

		foreach ($ruleSet['rules'] as $rule) {
			$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition']);
		}

		return $ruleSet;
	}

	/**
	 * Replace a rule set's metadata and full rules array. Rules the client sends back with a
	 * known id keep that id and their broken state, so a metadata-only change (for example
	 * toggling the switch in the column header popover) does not recreate every rule.
	 *
	 * @return array the updated rule set
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 * @throws InvalidArgumentException
	 */
	public function updateRuleSet(int $viewId, string $ruleSetId, string $userId, FormattingRuleSetInput $input): array {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		$rsIndex = $this->findRuleSetIndex($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$this->checkColumnOwnership($view->getTableId(), $input->getTargetCol(), $input->getRules());

		$existing = $formatting[$rsIndex];
		$storedRulesById = [];
		foreach ($existing['rules'] ?? [] as $storedRule) {
			$storedRulesById[$storedRule['id']] = $storedRule;
		}

		$existing['title'] = $input->getTitle();
		$existing['targetType'] = $input->getTargetType();
		$existing['targetCol'] = $input->getTargetCol();
		$existing['mode'] = $input->getMode();
		$existing['enabled'] = $input->isEnabled();
		$existing['rules'] = [];
		foreach ($input->getRules() as $sortOrder => $ruleInput) {
			$ruleId = $ruleInput->getId();
			$priorRule = $ruleId !== null ? ($storedRulesById[$ruleId] ?? null) : null;
			$existing['rules'][] = $this->buildRuleData($ruleInput, $sortOrder, $priorRule);
		}

		$formatting[$rsIndex] = $existing;
		$this->revalidateBrokenRules($view, $formatting);
		$this->validateViewLimits($formatting);
		$this->persistFormatting($view, $formatting);

		$keptIds = array_column($formatting[$rsIndex]['rules'], 'id');
		foreach (array_diff(array_keys($storedRulesById), $keptIds) as $removedId) {
			$this->ruleColMapper->deleteByRule($removedId);
		}
		foreach ($formatting[$rsIndex]['rules'] as $rule) {
			$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition']);
		}

		return $formatting[$rsIndex];
	}

	/**
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function deleteRuleSet(int $viewId, string $ruleSetId, string $userId): void {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		$rsIndex = $this->findRuleSetIndex($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		foreach ($formatting[$rsIndex]['rules'] ?? [] as $rule) {
			$this->ruleColMapper->deleteByRule($rule['id']);
		}

		array_splice($formatting, $rsIndex, 1);
		foreach ($formatting as $sortOrder => &$ruleSet) {
			$ruleSet['sortOrder'] = $sortOrder;
		}
		unset($ruleSet);

		$this->persistFormatting($view, $formatting);
	}

	/**
	 * @param string[] $orderedIds rule set IDs in the desired order
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function reorderRuleSets(int $viewId, string $userId, array $orderedIds): void {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		$ruleSetsById = [];
		foreach ($formatting as $ruleSet) {
			$ruleSetsById[$ruleSet['id']] = $ruleSet;
		}

		$reordered = [];
		foreach ($orderedIds as $sortOrder => $id) {
			if (!isset($ruleSetsById[$id])) {
				throw new NotFoundError('Rule set not found: ' . $id);
			}
			$ruleSet = $ruleSetsById[$id];
			$ruleSet['sortOrder'] = $sortOrder;
			$reordered[] = $ruleSet;
			unset($ruleSetsById[$id]);
		}
		foreach ($ruleSetsById as $ruleSet) {
			$ruleSet['sortOrder'] = count($reordered);
			$reordered[] = $ruleSet;
		}

		$this->persistFormatting($view, $reordered);
	}

	/**
	 * @return array the created rule (including generated id and sortOrder)
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 * @throws InvalidArgumentException
	 */
	public function createRule(int $viewId, string $ruleSetId, string $userId, FormattingRuleInput $input): array {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		$rsIndex = $this->findRuleSetIndex($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$this->checkColumnOwnership($view->getTableId(), null, [$input]);

		$rule = $this->buildRuleData($input, count($formatting[$rsIndex]['rules'] ?? []));
		$formatting[$rsIndex]['rules'][] = $rule;

		$this->revalidateBrokenRules($view, $formatting);
		$this->validateViewLimits($formatting);
		$this->persistFormatting($view, $formatting);
		$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition']);

		return $this->findRule($formatting[$rsIndex]['rules'], $rule['id']) ?? $rule;
	}

	/**
	 * @return array the updated rule
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 * @throws InvalidArgumentException
	 */
	public function updateRule(int $viewId, string $ruleSetId, string $ruleId, string $userId, FormattingRuleInput $input): array {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		$rsIndex = $this->findRuleSetIndex($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$ruleIndex = $this->findRuleIndex($formatting[$rsIndex]['rules'] ?? [], $ruleId);
		if ($ruleIndex === -1) {
			throw new NotFoundError('Rule not found: ' . $ruleId);
		}

		$this->checkColumnOwnership($view->getTableId(), null, [$input]);

		$updated = $formatting[$rsIndex]['rules'][$ruleIndex];
		$updated['title'] = $input->getTitle();
		$updated['enabled'] = $input->isEnabled();
		$updated['condition'] = $input->getCondition()->toArray();
		$updated['format'] = $input->getFormat()->toArray();

		$formatting[$rsIndex]['rules'][$ruleIndex] = $updated;
		$this->revalidateBrokenRules($view, $formatting);
		$this->validateViewLimits($formatting);
		$this->persistFormatting($view, $formatting);
		$this->syncJunctionIndex($ruleId, $viewId, $updated['condition']);

		return $formatting[$rsIndex]['rules'][$ruleIndex];
	}

	/**
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function deleteRule(int $viewId, string $ruleSetId, string $ruleId, string $userId): void {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		$rsIndex = $this->findRuleSetIndex($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$ruleIndex = $this->findRuleIndex($formatting[$rsIndex]['rules'] ?? [], $ruleId);
		if ($ruleIndex === -1) {
			throw new NotFoundError('Rule not found: ' . $ruleId);
		}

		array_splice($formatting[$rsIndex]['rules'], $ruleIndex, 1);
		$this->persistFormatting($view, $formatting);
		$this->ruleColMapper->deleteByRule($ruleId);
	}

	/**
	 * Mark all rules referencing this column as broken and remove junction entries.
	 */
	public function handleColumnDeletion(int $columnId): void {
		try {
			$affected = $this->ruleColMapper->findRuleIdsByColumn($columnId);
			if (empty($affected)) {
				return;
			}

			foreach ($this->groupByViewId($affected) as $viewId => $ruleIds) {
				$this->markBrokenInView($viewId, $ruleIds, 'column deletion');
			}

			$this->ruleColMapper->deleteByColumn($columnId);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to handle column deletion in formatting', ['exception' => $e]);
		}
	}

	/**
	 * Mark all rules referencing this column as broken (column still exists, type changed).
	 */
	public function handleColumnTypeChange(int $columnId, string $newType): void {
		try {
			$affected = $this->ruleColMapper->findRuleIdsByColumn($columnId);
			if (empty($affected)) {
				return;
			}

			foreach ($this->groupByViewId($affected) as $viewId => $ruleIds) {
				$this->markBrokenInView($viewId, $ruleIds, 'column type change');
			}
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to handle column type change in formatting', ['exception' => $e]);
		}
	}

	/**
	 * Mark rules as broken where a condition value references the deleted selection option.
	 */
	public function handleSelectionOptionDeletion(int $columnId, int $optionId): void {
		try {
			$affected = $this->ruleColMapper->findRuleIdsByColumn($columnId);
			if (empty($affected)) {
				return;
			}

			$magicValue = '@selection-id-' . $optionId;
			foreach ($this->groupByViewId($affected) as $viewId => $ruleIds) {
				$this->markBrokenInView($viewId, $ruleIds, 'selection option deletion', $magicValue);
			}
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to handle selection option deletion in formatting', ['exception' => $e]);
		}
	}

	/**
	 * Mark the given rules of one view as broken and persist, optionally only those that
	 * reference the given selection option value.
	 *
	 * @param string[] $ruleIds
	 */
	private function markBrokenInView(int $viewId, array $ruleIds, string $reason, ?string $magicValue = null): void {
		try {
			$view = $this->viewMapper->find($viewId);
			$formatting = $this->loadFormatting($view);
			if (!$this->markRulesBroken($formatting, $ruleIds, $magicValue)) {
				return;
			}
			$this->persistFormatting($view, $formatting);
		} catch (\Exception $e) {
			$this->logger->warning('Could not mark rules broken after ' . $reason, ['exception' => $e]);
		}
	}

	/** @throws PermissionError */
	private function checkPermission(int $viewId, string $userId): void {
		if (!$this->permissionsService->canManageViewById($viewId, $userId)) {
			throw new PermissionError('PermissionError: cannot manage formatting for view ' . $viewId);
		}
	}

	/**
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	private function loadView(int $viewId): View {
		try {
			return $this->viewMapper->find($viewId);
		} catch (DoesNotExistException $e) {
			throw new NotFoundError('View not found: ' . $viewId);
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}
	}

	private function loadFormatting(View $view): array {
		$json = $view->getFormatting();
		if ($json === null || $json === '' || $json === 'null') {
			return [];
		}
		$decoded = json_decode($json, true);
		return is_array($decoded) ? $decoded : [];
	}

	/** @throws InternalError */
	private function persistFormatting(View $view, array $formatting): void {
		try {
			$view->setFormatting(json_encode($formatting));
			$this->viewMapper->update($view);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}
	}

	/** @throws InvalidArgumentException */
	private function validateViewLimits(array $formatting): void {
		if (count($formatting) > self::MAX_RULE_SETS_PER_VIEW) {
			throw new InvalidArgumentException('Maximum of ' . self::MAX_RULE_SETS_PER_VIEW . ' rule sets per view exceeded');
		}
		foreach ($formatting as $ruleSet) {
			if (count($ruleSet['rules'] ?? []) > self::MAX_RULES_PER_RULE_SET) {
				throw new InvalidArgumentException('Maximum of ' . self::MAX_RULES_PER_RULE_SET . ' rules per rule set exceeded');
			}
		}
		if (strlen((string)json_encode($formatting)) > self::MAX_FORMATTING_BYTES) {
			throw new InvalidArgumentException('Formatting configuration exceeds the ' . self::MAX_FORMATTING_BYTES . ' byte limit');
		}
	}

	/**
	 * @param FormattingRuleInput[] $rules
	 * @throws InternalError
	 * @throws InvalidArgumentException
	 */
	private function checkColumnOwnership(int $tableId, ?int $targetCol, array $rules): void {
		try {
			$validIds = array_flip(array_map('intval', $this->columnMapper->findAllIdsByTable($tableId)));
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError('Failed to validate column ownership');
		}

		if ($targetCol !== null && !isset($validIds[$targetCol])) {
			throw new InvalidArgumentException('Target column ' . $targetCol . ' does not belong to this view\'s table');
		}
		foreach ($rules as $ruleInput) {
			foreach ($ruleInput->getCondition()->collectColumnIds() as $columnId) {
				if (!isset($validIds[$columnId])) {
					throw new InvalidArgumentException('Column ' . $columnId . ' does not belong to this view\'s table');
				}
			}
		}
	}

	private function syncJunctionIndex(string $ruleId, int $viewId, array $conditionSet): void {
		try {
			$this->ruleColMapper->syncForRule($ruleId, $viewId, $this->extractColumnIdsFromConditionSet($conditionSet));
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to sync formatting junction index', ['exception' => $e]);
		}
	}

	/** @return int[] */
	private function extractColumnIdsFromConditionSet(array $conditionSet): array {
		$columnIds = [];
		foreach ($conditionSet['groups'] ?? [] as $group) {
			foreach ($group['conditions'] ?? [] as $condition) {
				if (isset($condition['columnId'])) {
					$columnIds[] = (int)$condition['columnId'];
				}
			}
		}
		return array_values(array_unique($columnIds));
	}

	/**
	 * Clear the broken flag of rules whose conditions match the live columns again. Only
	 * mutates the given array; the caller persists.
	 */
	private function revalidateBrokenRules(View $view, array &$formatting): void {
		if (!$this->hasBrokenRule($formatting)) {
			return;
		}

		$typeMap = $this->buildColumnTypeMap($view->getTableId());

		foreach ($formatting as &$ruleSet) {
			if (!isset($ruleSet['rules']) || !is_array($ruleSet['rules'])) {
				continue;
			}
			foreach ($ruleSet['rules'] as &$rule) {
				if (!($rule['broken'] ?? false)) {
					continue;
				}
				if ($this->allConditionsValid($rule['condition'] ?? [], $typeMap)) {
					$rule['broken'] = false;
					$rule['enabled'] = true;
				}
			}
			unset($rule);
		}
		unset($ruleSet);
	}

	private function hasBrokenRule(array $formatting): bool {
		foreach ($formatting as $ruleSet) {
			foreach ($ruleSet['rules'] ?? [] as $rule) {
				if ($rule['broken'] ?? false) {
					return true;
				}
			}
		}
		return false;
	}

	private function allConditionsValid(array $conditionSet, array $typeMap): bool {
		foreach ($conditionSet['groups'] ?? [] as $group) {
			foreach ($group['conditions'] ?? [] as $condition) {
				$columnId = (int)($condition['columnId'] ?? 0);
				if (!isset($typeMap[$columnId]) || $typeMap[$columnId] !== ($condition['columnType'] ?? null)) {
					return false;
				}
			}
		}
		return true;
	}

	/** @return array<int, string> */
	private function buildColumnTypeMap(int $tableId): array {
		try {
			$columns = $this->columnMapper->findAllByTable($tableId);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return [];
		}
		$typeMap = [];
		foreach ($columns as $column) {
			$subtype = $column->getSubtype();
			$typeMap[$column->getId()] = $subtype ? $column->getType() . '-' . $subtype : $column->getType();
		}
		return $typeMap;
	}

	/**
	 * Build the stored representation of a rule. When the client sent back a rule that is
	 * already stored, its identity and broken state are carried over.
	 */
	private function buildRuleData(FormattingRuleInput $input, int $sortOrder, ?array $storedRule = null): array {
		return [
			'id' => $storedRule['id'] ?? $this->generateUuid(),
			'title' => $input->getTitle(),
			'sortOrder' => $sortOrder,
			'enabled' => $input->isEnabled(),
			'broken' => $storedRule['broken'] ?? false,
			'condition' => $input->getCondition()->toArray(),
			'format' => $input->getFormat()->toArray(),
		];
	}

	/** @return int the index, or -1 when not found */
	private function findRuleSetIndex(array $formatting, string $ruleSetId): int {
		foreach ($formatting as $index => $ruleSet) {
			if (($ruleSet['id'] ?? null) === $ruleSetId) {
				return $index;
			}
		}
		return -1;
	}

	/** @return int the index, or -1 when not found */
	private function findRuleIndex(array $rules, string $ruleId): int {
		foreach ($rules as $index => $rule) {
			if (($rule['id'] ?? null) === $ruleId) {
				return $index;
			}
		}
		return -1;
	}

	private function findRule(array $rules, string $ruleId): ?array {
		$index = $this->findRuleIndex($rules, $ruleId);
		return $index === -1 ? null : $rules[$index];
	}

	/**
	 * Mark the given rules as broken and disabled. With a magic value given, only rules that
	 * reference that value are marked.
	 *
	 * @param string[] $ruleIds
	 * @return bool whether anything changed
	 */
	private function markRulesBroken(array &$formatting, array $ruleIds, ?string $magicValue = null): bool {
		$ruleIdSet = array_flip($ruleIds);
		$changed = false;

		foreach ($formatting as &$ruleSet) {
			if (!isset($ruleSet['rules']) || !is_array($ruleSet['rules'])) {
				continue;
			}
			foreach ($ruleSet['rules'] as &$rule) {
				if (!isset($ruleIdSet[$rule['id'] ?? ''])) {
					continue;
				}
				if ($magicValue !== null && !$this->ruleUsesValue($rule, $magicValue)) {
					continue;
				}
				$rule['broken'] = true;
				$rule['enabled'] = false;
				$changed = true;
			}
			unset($rule);
		}
		unset($ruleSet);

		return $changed;
	}

	private function ruleUsesValue(array $rule, string $needle): bool {
		foreach ($rule['condition']['groups'] ?? [] as $group) {
			foreach ($group['conditions'] ?? [] as $condition) {
				$value = $condition['value'] ?? null;
				if (is_array($value) ? in_array($needle, $value, true) : $value === $needle) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param list<array{rule_id: string, view_id: int}> $affected
	 * @return array<int, string[]> view_id => rule_id[]
	 */
	private function groupByViewId(array $affected): array {
		$byView = [];
		foreach ($affected as $row) {
			$byView[$row['view_id']][] = $row['rule_id'];
		}
		return $byView;
	}

	private function generateUuid(): string {
		$data = random_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
}
