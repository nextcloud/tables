<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

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
	 */
	public function saveForView(int $viewId, array $formatting): void {
		$view = $this->loadView($viewId);
		$this->persistFormatting($view, $formatting);

		$this->ruleColMapper->deleteByView($viewId);
		foreach ($formatting as $ruleSet) {
			foreach ($ruleSet['rules'] ?? [] as $rule) {
				$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition']);
			}
		}
	}

	/**
	 * @return array the created rule set (including generated id and sortOrder)
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function createRuleSet(int $viewId, string $userId, FormattingRuleSetInput $input): array {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		$this->checkColumnOwnership($view->getTableId(), $input->getTargetCol(), $input->getRules());

		$ruleSetId = $this->generateUuid();
		$ruleSet = [
			'id' => $ruleSetId,
			'title' => $input->getTitle(),
			'targetType' => $input->getTargetType(),
			'targetCol' => $input->getTargetCol(),
			'mode' => $input->getMode(),
			'sortOrder' => count($formatting),
			'enabled' => $input->isEnabled(),
			'broken' => false,
			'rules' => [],
		];
		foreach ($input->getRules() as $ruleInput) {
			$ruleSet['rules'][] = $this->buildRuleData($ruleInput, count($ruleSet['rules']));
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
	 * Replace a rule set's metadata and full rules array.
	 *
	 * @return array the updated rule set
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function updateRuleSet(int $viewId, string $ruleSetId, string $userId, FormattingRuleSetInput $input): array {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		[$rsIndex] = $this->findRuleSetIndex($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$this->checkColumnOwnership($view->getTableId(), $input->getTargetCol(), $input->getRules());

		foreach ($formatting[$rsIndex]['rules'] as $oldRule) {
			$this->ruleColMapper->deleteByRule($oldRule['id']);
		}

		$existing = $formatting[$rsIndex];
		$existing['title'] = $input->getTitle();
		$existing['targetType'] = $input->getTargetType();
		$existing['targetCol'] = $input->getTargetCol();
		$existing['mode'] = $input->getMode();
		$existing['enabled'] = $input->isEnabled();
		$existing['rules'] = [];
		foreach ($input->getRules() as $ruleInput) {
			$existing['rules'][] = $this->buildRuleData($ruleInput, count($existing['rules']));
		}

		$formatting[$rsIndex] = $existing;
		$this->validateViewLimits($formatting);
		$this->persistFormatting($view, $formatting);

		foreach ($existing['rules'] as $rule) {
			$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition']);
		}

		$this->revalidateBrokenRules($view, $formatting);

		return $existing;
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

		[$rsIndex] = $this->findRuleSetIndex($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		foreach ($formatting[$rsIndex]['rules'] as $rule) {
			$this->ruleColMapper->deleteByRule($rule['id']);
		}

		array_splice($formatting, $rsIndex, 1);
		foreach ($formatting as $idx => &$rs) {
			$rs['sortOrder'] = $idx;
		}
		unset($rs);

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

		$rsMap = [];
		foreach ($formatting as $rs) {
			$rsMap[$rs['id']] = $rs;
		}

		$reordered = [];
		foreach ($orderedIds as $sortOrder => $id) {
			if (!isset($rsMap[$id])) {
				throw new NotFoundError('Rule set not found: ' . $id);
			}
			$rs = $rsMap[$id];
			$rs['sortOrder'] = $sortOrder;
			$reordered[] = $rs;
			unset($rsMap[$id]);
		}
		foreach ($rsMap as $rs) {
			$rs['sortOrder'] = count($reordered);
			$reordered[] = $rs;
		}

		$this->persistFormatting($view, $reordered);
	}

	/**
	 * @return array the created rule (including generated id and sortOrder)
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function createRule(int $viewId, string $ruleSetId, string $userId, FormattingRuleInput $input): array {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		[$rsIndex] = $this->findRuleSetIndex($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$this->checkColumnOwnership($view->getTableId(), null, [$input]);

		$rule = $this->buildRuleData($input, count($formatting[$rsIndex]['rules']));
		$formatting[$rsIndex]['rules'][] = $rule;

		$this->validateViewLimits($formatting);
		$this->persistFormatting($view, $formatting);
		$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition']);
		$this->revalidateBrokenRules($view, $formatting);

		return $rule;
	}

	/**
	 * @return array the updated rule
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function updateRule(int $viewId, string $ruleSetId, string $ruleId, string $userId, FormattingRuleInput $input): array {
		$this->checkPermission($viewId, $userId);

		$view = $this->loadView($viewId);
		$formatting = $this->loadFormatting($view);

		[$rsIndex] = $this->findRuleSetIndex($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$ruleIndex = $this->findRuleIndex($formatting[$rsIndex]['rules'], $ruleId);
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
		$this->persistFormatting($view, $formatting);
		$this->syncJunctionIndex($ruleId, $viewId, $updated['condition']);
		$this->revalidateBrokenRules($view, $formatting);

		return $updated;
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

		[$rsIndex] = $this->findRuleSetIndex($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$ruleIndex = $this->findRuleIndex($formatting[$rsIndex]['rules'], $ruleId);
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

			$byView = $this->groupByViewId($affected);
			foreach ($byView as $viewId => $ruleIds) {
				try {
					$view = $this->viewMapper->find($viewId);
					$formatting = $this->loadFormatting($view);
					$this->markRulesBroken($formatting, $ruleIds);
					$this->persistFormatting($view, $formatting);
				} catch (\Exception $e) {
					$this->logger->warning('Could not mark rules broken after column deletion', ['exception' => $e]);
				}
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

			$byView = $this->groupByViewId($affected);
			foreach ($byView as $viewId => $ruleIds) {
				try {
					$view = $this->viewMapper->find($viewId);
					$formatting = $this->loadFormatting($view);
					$this->markRulesBroken($formatting, $ruleIds);
					$this->persistFormatting($view, $formatting);
				} catch (\Exception $e) {
					$this->logger->warning('Could not mark rules broken after column type change', ['exception' => $e]);
				}
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

			$magic = '@selection-id-' . $optionId;
			$byView = $this->groupByViewId($affected);

			foreach ($byView as $viewId => $ruleIds) {
				try {
					$view = $this->viewMapper->find($viewId);
					$formatting = $this->loadFormatting($view);
					$changed = $this->markRulesBrokenIfOptionUsed($formatting, $ruleIds, $magic);
					if ($changed) {
						$this->persistFormatting($view, $formatting);
					}
				} catch (\Exception $e) {
					$this->logger->warning('Could not mark rules broken after selection option deletion', ['exception' => $e]);
				}
			}
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to handle selection option deletion in formatting', ['exception' => $e]);
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

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
		return json_decode($json, true) ?? [];
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

	/** @throws InternalError */
	private function validateViewLimits(array $formatting): void {
		if (count($formatting) > 50) {
			throw new InternalError('Maximum of 50 rule sets per view exceeded');
		}
		foreach ($formatting as $rs) {
			if (count($rs['rules'] ?? []) > 20) {
				throw new InternalError('Maximum of 20 rules per rule set exceeded');
			}
		}
		if (strlen((string)json_encode($formatting)) > 65536) {
			throw new InternalError('Formatting configuration exceeds 64 KB limit');
		}
	}

	/**
	 * @param FormattingRuleInput[] $rules
	 * @throws InternalError
	 */
	private function checkColumnOwnership(int $tableId, ?int $targetCol, array $rules): void {
		try {
			$validIds = array_flip(array_map('intval', $this->columnMapper->findAllIdsByTable($tableId)));
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError('Failed to validate column ownership');
		}

		if ($targetCol !== null && !isset($validIds[$targetCol])) {
			throw new InternalError('Target column ' . $targetCol . ' does not belong to this view\'s table');
		}
		foreach ($rules as $ruleInput) {
			foreach ($ruleInput->getCondition()->collectColumnIds() as $columnId) {
				if (!isset($validIds[$columnId])) {
					throw new InternalError('Column ' . $columnId . ' does not belong to this view\'s table');
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

	private function extractColumnIdsFromConditionSet(array $conditionSet): array {
		$ids = [];
		foreach ($conditionSet['groups'] ?? [] as $group) {
			foreach ($group['conditions'] ?? [] as $c) {
				$ids[] = (int)$c['columnId'];
			}
		}
		return array_values(array_unique($ids));
	}

	private function revalidateBrokenRules(View $view, array &$formatting): void {
		$hasBroken = false;
		foreach ($formatting as $rs) {
			foreach ($rs['rules'] ?? [] as $rule) {
				if ($rule['broken'] ?? false) {
					$hasBroken = true;
					break 2;
				}
			}
		}
		if (!$hasBroken) {
			return;
		}

		$typeMap = $this->buildColumnTypeMap($view->getTableId());
		$changed = false;

		foreach ($formatting as &$ruleSet) {
			foreach ($ruleSet['rules'] as &$rule) {
				if (!($rule['broken'] ?? false)) {
					continue;
				}
				$allValid = $this->allConditionsValid($rule['condition'], $typeMap);
				if ($allValid) {
					$rule['broken'] = false;
					$rule['enabled'] = true;
					$changed = true;
				}
			}
			unset($rule);
		}
		unset($ruleSet);

		if ($changed) {
			$this->persistFormatting($view, $formatting);
		}
	}

	private function allConditionsValid(array $conditionSet, array $typeMap): bool {
		foreach ($conditionSet['groups'] ?? [] as $group) {
			foreach ($group['conditions'] ?? [] as $c) {
				$columnId = (int)$c['columnId'];
				if (!isset($typeMap[$columnId])) {
					return false;
				}
				if ($typeMap[$columnId] !== $c['columnType']) {
					return false;
				}
			}
		}
		return true;
	}

	private function buildColumnTypeMap(int $tableId): array {
		try {
			$columns = $this->columnMapper->findAllByTable($tableId);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return [];
		}
		$map = [];
		foreach ($columns as $col) {
			$type = $col->getType();
			$subtype = $col->getSubtype();
			$map[$col->getId()] = $subtype ? $type . '-' . $subtype : $type;
		}
		return $map;
	}

	private function buildRuleData(FormattingRuleInput $input, int $sortOrder): array {
		return [
			'id' => $this->generateUuid(),
			'title' => $input->getTitle(),
			'sortOrder' => $sortOrder,
			'enabled' => $input->isEnabled(),
			'broken' => false,
			'condition' => $input->getCondition()->toArray(),
			'format' => $input->getFormat()->toArray(),
		];
	}

	/** @return array{int, array|null} [index, ruleSet] — index is -1 when not found */
	private function findRuleSetIndex(array $formatting, string $ruleSetId): array {
		foreach ($formatting as $idx => $rs) {
			if ($rs['id'] === $ruleSetId) {
				return [$idx, $rs];
			}
		}
		return [-1, null];
	}

	private function findRuleIndex(array $rules, string $ruleId): int {
		foreach ($rules as $idx => $r) {
			if ($r['id'] === $ruleId) {
				return $idx;
			}
		}
		return -1;
	}

	private function markRulesBroken(array &$formatting, array $ruleIds): void {
		$ruleIdSet = array_flip($ruleIds);
		foreach ($formatting as &$ruleSet) {
			foreach ($ruleSet['rules'] as &$rule) {
				if (isset($ruleIdSet[$rule['id']])) {
					$rule['broken'] = true;
					$rule['enabled'] = false;
				}
			}
			unset($rule);
		}
		unset($ruleSet);
	}

	private function markRulesBrokenIfOptionUsed(array &$formatting, array $ruleIds, string $magic): bool {
		$ruleIdSet = array_flip($ruleIds);
		$changed = false;
		foreach ($formatting as &$ruleSet) {
			foreach ($ruleSet['rules'] as &$rule) {
				if (!isset($ruleIdSet[$rule['id']])) {
					continue;
				}
				if ($this->ruleUsesSelectionMagic($rule, $magic)) {
					$rule['broken'] = true;
					$rule['enabled'] = false;
					$changed = true;
				}
			}
			unset($rule);
		}
		unset($ruleSet);
		return $changed;
	}

	private function ruleUsesSelectionMagic(array $rule, string $magic): bool {
		foreach ($rule['condition']['groups'] ?? [] as $group) {
			foreach ($group['conditions'] ?? [] as $c) {
				if (isset($c['value']) && $c['value'] === $magic) {
					return true;
				}
				if (!empty($c['values']) && in_array($magic, (array)$c['values'], true)) {
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
