<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use InvalidArgumentException;
use OCA\Tables\Constants\SelectionReference;
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
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Log\LoggerInterface;

class FormattingService extends SuperService {
	public const MAX_RULE_SETS_PER_VIEW = 50;
	private const MAX_RULES_PER_RULE_SET = FormattingRuleSetInput::MAX_RULES;

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
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws InvalidArgumentException
	 */
	public function saveForView(int $viewId, array $formatting): void {
		$view = $this->loadView($viewId);
		$validIds = $this->loadValidColumnIds($view->getTableId());
		$normalised = $this->normaliseImportedFormatting($formatting, $validIds);
		$this->persistFormatting($view, $normalised, $this->validateViewLimits($normalised));

		try {
			$this->ruleColMapper->deleteByView($viewId);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}
		foreach ($normalised as $ruleSet) {
			foreach ($ruleSet['rules'] as $rule) {
				$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition'], self::targetColOf($ruleSet), $validIds, replaceExisting: false);
			}
			$this->syncEmptyRuleSetIndex($ruleSet, $viewId, $validIds);
		}
	}

	/**
	 * @param array<int, int> $validIds the table's column ids as keys
	 * @throws InternalError
	 * @throws InvalidArgumentException
	 */
	private function normaliseImportedFormatting(array $formatting, array $validIds): array {
		$normalised = [];
		foreach (array_values($formatting) as $sortOrder => $ruleSet) {
			if (!is_array($ruleSet)) {
				throw new InvalidArgumentException('Each formatting entry must be a rule set');
			}

			$input = FormattingRuleSetInput::createFromInputArray($ruleSet);

			$storedRules = array_values(is_array($ruleSet['rules'] ?? null) ? $ruleSet['rules'] : []);
			$rules = [];
			foreach ($input->getRules() as $ruleSortOrder => $ruleInput) {
				$rule = $this->buildRuleData($ruleInput, $ruleSortOrder);
				$rule['broken'] = (bool)($storedRules[$ruleSortOrder]['broken'] ?? false)
					|| !$this->allColumnsKnown($ruleInput->getCondition()->collectColumnIds(), $validIds);
				$rules[] = $rule;
			}

			$targetCol = $input->getTargetCol();
			$normalised[] = [
				'id' => $this->generateUuid(),
				'title' => $input->getTitle(),
				'targetType' => $input->getTargetType(),
				'targetCol' => $targetCol,
				'mode' => $input->getMode(),
				'marker' => $input->getMarker(),
				'sortOrder' => $sortOrder,
				'enabled' => $input->isEnabled(),
				'broken' => (bool)($ruleSet['broken'] ?? false)
					|| ($targetCol !== null && !$this->allColumnsKnown([$targetCol], $validIds)),
				'rules' => $rules,
			];
		}

		return $normalised;
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

		$validIds = $this->checkColumnOwnership($view, $input->getTargetCol(), $input->getRules());
		$seesEveryRule = $this->seesEveryRule($view, $userId);

		$ruleSet = [
			'id' => $this->generateUuid(),
			'title' => $input->getTitle(),
			'targetType' => $input->getTargetType(),
			'targetCol' => $input->getTargetCol(),
			'mode' => $input->getMode(),
			'marker' => $input->getMarker(),
			'sortOrder' => count($formatting),
			'enabled' => $input->isEnabled(),
			'broken' => false,
			'rules' => [],
		];
		foreach ($input->getRules() as $sortOrder => $ruleInput) {
			$ruleSet['rules'][] = $this->buildRuleData($ruleInput, $sortOrder);
		}

		$formatting[] = $ruleSet;
		$this->persistFormatting($view, $formatting, $this->validateViewLimits(
			$formatting,
			$seesEveryRule ? null : $view->pruneFormattingForRecipient($formatting),
		));

		foreach ($ruleSet['rules'] as $rule) {
			$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition'], self::targetColOf($ruleSet), $validIds);
		}
		$this->syncEmptyRuleSetIndex($ruleSet, $viewId, $validIds);

		return self::withCallerPositions($ruleSet, $seesEveryRule ? $formatting : $view->pruneFormattingForRecipient($formatting));
	}

	/**
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

		$rsIndex = self::findIndexById($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$seesEveryRule = $this->seesEveryRule($view, $userId);
		$existing = $formatting[$rsIndex];
		$storedRulesById = [];
		foreach ($existing['rules'] ?? [] as $storedRule) {
			$storedRulesById[$storedRule['id'] ?? ''] = $storedRule;
		}

		$validIds = $this->checkColumnOwnership(
			$view,
			self::carriesStoredTargetCol($existing, $input) ? null : $input->getTargetCol(),
			self::rulesIntroducingColumns($input->getRules(), $storedRulesById),
		);

		$existing['title'] = $input->getTitle();
		$existing['targetType'] = $input->getTargetType();
		$existing['targetCol'] = $input->getTargetCol();
		$existing['mode'] = $input->getMode();
		$existing['marker'] = $input->getMarker();
		$existing['enabled'] = $input->isEnabled();
		$existing['rules'] = [];
		$submittedIds = [];
		foreach ($input->getRules() as $sortOrder => $ruleInput) {
			$ruleId = $ruleInput->getId();
			if ($ruleId !== null && isset($submittedIds[$ruleId])) {
				throw new InvalidArgumentException('Duplicate rule id in payload: ' . $ruleId);
			}
			$priorRule = $ruleId !== null ? ($storedRulesById[$ruleId] ?? null) : null;
			if ($ruleId !== null) {
				$submittedIds[$ruleId] = true;
			}
			$existing['rules'][] = $this->buildRuleData($ruleInput, $sortOrder, $priorRule);
		}

		$preservedIds = [];
		foreach ($storedRulesById as $storedId => $storedRule) {
			if (isset($submittedIds[$storedId])) {
				continue;
			}
			$position = min((int)($storedRule['sortOrder'] ?? count($existing['rules'])), count($existing['rules']));
			array_splice($existing['rules'], max($position, 0), 0, [$storedRule]);
			$preservedIds[$storedId] = true;
		}
		foreach ($existing['rules'] as $sortOrder => &$rule) {
			$rule['sortOrder'] = $sortOrder;
		}
		unset($rule);

		$formatting[$rsIndex] = $existing;
		$this->revalidateBrokenRules($view, $formatting);
		$visibleSets = $seesEveryRule ? $formatting : $view->pruneFormattingForRecipient($formatting);
		$this->persistFormatting($view, $formatting, $this->validateViewLimits(
			$formatting,
			$seesEveryRule ? null : $visibleSets,
		));

		foreach ($formatting[$rsIndex]['rules'] as $rule) {
			$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition'], self::targetColOf($formatting[$rsIndex]), $validIds);
		}
		$this->syncEmptyRuleSetIndex($formatting[$rsIndex], $viewId, $validIds);

		$result = $formatting[$rsIndex];
		$result['rules'] = array_values(array_filter(
			$result['rules'],
			static fn (array $rule): bool => !isset($preservedIds[$rule['id'] ?? '']),
		));

		return self::withCallerPositions($result, $visibleSets);
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

		$rsIndex = self::findIndexById($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$removedRules = $formatting[$rsIndex]['rules'] ?? [];

		array_splice($formatting, $rsIndex, 1);
		foreach ($formatting as $sortOrder => &$ruleSet) {
			$ruleSet['sortOrder'] = $sortOrder;
		}
		unset($ruleSet);

		$this->persistFormatting($view, $formatting);
		$this->deleteJunctionRows(array_column($removedRules, 'id'));
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
		foreach ($orderedIds as $id) {
			if (!isset($ruleSetsById[$id])) {
				throw new NotFoundError('Rule set not found: ' . $id);
			}
			$reordered[] = $ruleSetsById[$id];
			unset($ruleSetsById[$id]);
		}
		foreach ($ruleSetsById as $ruleSet) {
			$position = min((int)($ruleSet['sortOrder'] ?? count($reordered)), count($reordered));
			array_splice($reordered, max($position, 0), 0, [$ruleSet]);
		}
		foreach ($reordered as $sortOrder => &$ruleSet) {
			$ruleSet['sortOrder'] = $sortOrder;
		}
		unset($ruleSet);

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

		$rsIndex = self::findIndexById($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$validIds = $this->checkColumnOwnership($view, null, [$input]);
		$seesEveryRule = $this->seesEveryRule($view, $userId);

		$rule = $this->buildRuleData($input, count($formatting[$rsIndex]['rules'] ?? []));
		$formatting[$rsIndex]['rules'][] = $rule;

		$this->revalidateBrokenRules($view, $formatting);
		$visibleSets = $seesEveryRule ? $formatting : $view->pruneFormattingForRecipient($formatting);
		$this->persistFormatting($view, $formatting, $this->validateViewLimits(
			$formatting,
			$seesEveryRule ? null : $visibleSets,
		));
		$this->syncJunctionIndex($rule['id'], $viewId, $rule['condition'], self::targetColOf($formatting[$rsIndex]), $validIds);

		return self::callerVisibleRule($formatting[$rsIndex], $rule['id'], $visibleSets) ?? $rule;
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

		$rsIndex = self::findIndexById($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$ruleIndex = self::findIndexById($formatting[$rsIndex]['rules'] ?? [], $ruleId);
		if ($ruleIndex === -1) {
			throw new NotFoundError('Rule not found: ' . $ruleId);
		}

		$storedRule = $formatting[$rsIndex]['rules'][$ruleIndex];
		$validIds = $this->checkColumnOwnership(
			$view,
			null,
			self::introducesColumns($input, $storedRule) ? [$input] : [],
		);

		$updated = $storedRule;
		$updated['title'] = $input->getTitle();
		$updated['enabled'] = $input->isEnabled();
		$updated['condition'] = $input->getCondition()->toArray();
		$updated['format'] = $input->getFormat()->toArray();

		$formatting[$rsIndex]['rules'][$ruleIndex] = $updated;
		$this->revalidateBrokenRules($view, $formatting);
		$seesEveryRule = $this->seesEveryRule($view, $userId);
		$visibleSets = $seesEveryRule ? $formatting : $view->pruneFormattingForRecipient($formatting);
		$this->persistFormatting($view, $formatting, $this->validateViewLimits(
			$formatting,
			$seesEveryRule ? null : $visibleSets,
		));
		$this->syncJunctionIndex($ruleId, $viewId, $updated['condition'], self::targetColOf($formatting[$rsIndex]), $validIds);

		return self::callerVisibleRule($formatting[$rsIndex], $ruleId, $visibleSets) ?? $formatting[$rsIndex]['rules'][$ruleIndex];
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

		$rsIndex = self::findIndexById($formatting, $ruleSetId);
		if ($rsIndex === -1) {
			throw new NotFoundError('Rule set not found: ' . $ruleSetId);
		}

		$ruleIndex = self::findIndexById($formatting[$rsIndex]['rules'] ?? [], $ruleId);
		if ($ruleIndex === -1) {
			throw new NotFoundError('Rule not found: ' . $ruleId);
		}

		array_splice($formatting[$rsIndex]['rules'], $ruleIndex, 1);
		foreach ($formatting[$rsIndex]['rules'] as $sortOrder => &$rule) {
			$rule['sortOrder'] = $sortOrder;
		}
		unset($rule);

		$this->persistFormatting($view, $formatting);
		$this->deleteJunctionRows([$ruleId]);
	}

	public function handleColumnDeletion(int $columnId): void {
		$this->markRulesOfColumnBroken($columnId, 'column deletion', deletedColumnId: $columnId);

		try {
			$this->ruleColMapper->deleteByColumn($columnId);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to handle column deletion in formatting', ['exception' => $e]);
		}
	}

	public function handleColumnTypeChange(int $columnId): void {
		$this->markRulesOfColumnBroken($columnId, 'column type change');
	}

	/**
	 * @param int[] $optionIds
	 */
	public function handleSelectionOptionDeletion(int $columnId, array $optionIds): void {
		if ($optionIds === []) {
			return;
		}

		$magicValues = array_map(
			SelectionReference::forOptionId(...),
			$optionIds,
		);
		$this->markRulesOfColumnBroken($columnId, 'selection option deletion', $magicValues);
	}

	/**
	 * @param string[]|null $magicValues when given, only rules referencing one of these values
	 *                                   are marked
	 */
	private function markRulesOfColumnBroken(int $columnId, string $reason, ?array $magicValues = null, ?int $deletedColumnId = null): void {
		try {
			$affected = $this->ruleColMapper->findRuleIdsByColumn($columnId);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to handle ' . $reason . ' in formatting', ['exception' => $e]);
			return;
		}

		foreach ($this->groupByViewId($affected) as $viewId => $ruleIds) {
			$this->markBrokenInView($viewId, $ruleIds, $reason, $magicValues, $deletedColumnId);
		}
	}

	/**
	 * @param string[] $ruleIds
	 * @param string[]|null $magicValues
	 */
	private function markBrokenInView(int $viewId, array $ruleIds, string $reason, ?array $magicValues = null, ?int $deletedColumnId = null): void {
		try {
			$view = $this->viewMapper->find($viewId);
			$formatting = $this->loadFormatting($view);
			if (!$this->markRulesBroken($formatting, $ruleIds, $magicValues, $deletedColumnId)) {
				return;
			}
			$this->persistFormatting($view, $formatting);
		} catch (\Exception $e) {
			$this->logger->warning('Could not mark rules broken after ' . $reason, ['exception' => $e]);
		}
	}

	/** @throws PermissionError */
	/**
	 * @param list<array> $visibleSets the rule sets this caller is shown
	 */
	private static function callerVisibleRule(array $storedRuleSet, string $ruleId, array $visibleSets): ?array {
		$rule = self::findRule($storedRuleSet['rules'] ?? [], $ruleId);
		if ($rule === null) {
			return null;
		}
		foreach ($visibleSets as $candidate) {
			if (($candidate['id'] ?? null) !== ($storedRuleSet['id'] ?? null)) {
				continue;
			}
			foreach (array_values($candidate['rules'] ?? []) as $index => $visible) {
				if (($visible['id'] ?? null) === $ruleId) {
					$rule['sortOrder'] = $index;
					break 2;
				}
			}
		}

		return $rule;
	}

	/**
	 * @param array $ruleSet the rule set as it is about to be returned
	 * @param list<array> $visibleSets the rule sets this caller is shown, in stored order
	 */
	private static function withCallerPositions(array $ruleSet, array $visibleSets): array {
		foreach ($visibleSets as $index => $candidate) {
			if (($candidate['id'] ?? null) === ($ruleSet['id'] ?? null)) {
				$ruleSet['sortOrder'] = $index;
				break;
			}
		}
		$rules = array_values($ruleSet['rules'] ?? []);
		foreach ($rules as $index => $rule) {
			$rule['sortOrder'] = $index;
			$rules[$index] = $rule;
		}
		$ruleSet['rules'] = $rules;

		return $ruleSet;
	}

	private function seesEveryRule(View $view, string $userId): bool {
		return $this->permissionsService->canManageTableById($view->getTableId(), $userId);
	}

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
		} catch (DoesNotExistException) {
			throw new NotFoundError('View not found: ' . $viewId);
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}
	}

	private function loadFormatting(View $view): array {
		return $view->getFormattingArray();
	}

	/**
	 * @throws InternalError
	 * @throws InvalidArgumentException
	 */
	private function persistFormatting(View $view, array $formatting, ?string $encoded = null): void {
		try {
			$view->setFormatting($encoded ?? self::encodeFormatting($formatting));
			$this->viewMapper->update($view);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private static function encodeFormatting(array $formatting): string {
		$encoded = json_encode($formatting);
		if ($encoded === false) {
			throw new InvalidArgumentException('Formatting configuration is not encodable: ' . json_last_error_msg());
		}
		return $encoded;
	}

	/**
	 * @return string the encoded blob the byte cap was measured against, so that the caller can
	 *                store it instead of encoding the same array a second time
	 * @throws InvalidArgumentException
	 */
	private function validateViewLimits(array $formatting, ?array $countedForCaller = null): string {
		$counted = $countedForCaller ?? $formatting;
		if (count($counted) > self::MAX_RULE_SETS_PER_VIEW) {
			throw new InvalidArgumentException('Maximum of ' . self::MAX_RULE_SETS_PER_VIEW . ' rule sets per view exceeded');
		}
		foreach ($counted as $ruleSet) {
			if (count($ruleSet['rules'] ?? []) > self::MAX_RULES_PER_RULE_SET) {
				throw new InvalidArgumentException('Maximum of ' . self::MAX_RULES_PER_RULE_SET . ' rules per rule set exceeded');
			}
		}
		$encoded = self::encodeFormatting($formatting);
		if (strlen($encoded) > self::MAX_FORMATTING_BYTES) {
			throw new InvalidArgumentException('Formatting configuration exceeds the ' . self::MAX_FORMATTING_BYTES . ' byte limit');
		}
		return $encoded;
	}

	/**
	 * @param FormattingRuleInput[] $rules
	 * @return array<int, int> the view's column ids as keys, so the caller can reuse them
	 * @throws InvalidArgumentException
	 */
	private function checkColumnOwnership(View $view, ?int $targetCol, array $rules): array {
		$validIds = array_flip(array_map(intval(...), $view->getColumnIds()));

		if ($targetCol !== null && !$this->allColumnsKnown([$targetCol], $validIds)) {
			throw new InvalidArgumentException('Target column ' . $targetCol . ' is not part of this view');
		}
		if ($rules === []) {
			return $validIds;
		}

		[$typeMap] = $this->buildColumnMaps($view->getTableId());
		foreach ($rules as $ruleInput) {
			foreach ($ruleInput->getCondition()->toArray()['groups'] as $group) {
				foreach ($group['conditions'] as $condition) {
					$columnId = (int)$condition['columnId'];
					if (!$this->allColumnsKnown([$columnId], $validIds)) {
						throw new InvalidArgumentException('Column ' . $columnId . ' is not part of this view');
					}
					if (Column::isValidMetaTypeId($columnId)) {
						continue;
					}
					if (($typeMap[$columnId] ?? null) !== $condition['columnType']) {
						throw new InvalidArgumentException(
							'Column ' . $columnId . ' is of type ' . ($typeMap[$columnId] ?? 'unknown')
							. ', not ' . $condition['columnType'],
						);
					}
				}
			}
		}

		return $validIds;
	}

	private static function carriesStoredTargetCol(array $storedRuleSet, FormattingRuleSetInput $input): bool {
		$storedTargetCol = isset($storedRuleSet['targetCol']) ? (int)$storedRuleSet['targetCol'] : null;

		return (bool)($storedRuleSet['broken'] ?? false)
			&& $storedTargetCol !== null
			&& $storedTargetCol === $input->getTargetCol();
	}

	/**
	 * @param FormattingRuleInput[] $rules
	 * @param array<string, array> $storedRulesById
	 * @return list<FormattingRuleInput>
	 */
	private static function rulesIntroducingColumns(array $rules, array $storedRulesById): array {
		return array_values(array_filter($rules, static function (FormattingRuleInput $rule) use ($storedRulesById): bool {
			$ruleId = $rule->getId();

			return self::introducesColumns($rule, $ruleId !== null ? ($storedRulesById[$ruleId] ?? null) : null);
		}));
	}

	private static function introducesColumns(FormattingRuleInput $rule, ?array $storedRule): bool {
		return $storedRule === null
			|| !($storedRule['broken'] ?? false)
			|| ($storedRule['condition'] ?? null) !== $rule->getCondition()->toArray();
	}

	/**
	 * @return array<int, int> the table's column ids as keys
	 * @throws InternalError
	 */
	private function loadValidColumnIds(int $tableId): array {
		try {
			return array_flip(array_map(intval(...), $this->columnMapper->findAllIdsByTable($tableId)));
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError('Failed to validate column ownership');
		}
	}

	/**
	 * @param int[] $columnIds
	 * @param array<int, int> $validIds
	 */
	private function allColumnsKnown(array $columnIds, array $validIds): bool {
		foreach ($columnIds as $columnId) {
			if (!Column::isValidMetaTypeId($columnId) && !isset($validIds[$columnId])) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @throws InternalError
	 */
	/**
	 * @param array<int, int> $validIds
	 * @throws InternalError
	 */
	private function syncEmptyRuleSetIndex(array $ruleSet, int $viewId, array $validIds): void {
		if (($ruleSet['rules'] ?? []) !== []) {
			return;
		}
		$targetCol = self::targetColOf($ruleSet);
		if ($targetCol === null) {
			return;
		}

		$this->syncJunctionIndex($ruleSet['id'], $viewId, ['groups' => []], $targetCol, $validIds);
	}

	private function syncJunctionIndex(string $ruleId, int $viewId, array $conditionSet, ?int $targetCol = null, ?array $validIds = null, bool $replaceExisting = true): void {
		$columnIds = $this->extractColumnIdsFromConditionSet($conditionSet);
		if ($targetCol !== null && !in_array($targetCol, $columnIds, true)) {
			$columnIds[] = $targetCol;
		}
		if ($validIds !== null) {
			$columnIds = array_values(array_filter(
				$columnIds,
				fn (int $columnId): bool => $this->allColumnsKnown([$columnId], $validIds),
			));
		}

		try {
			if ($replaceExisting) {
				$this->ruleColMapper->syncForRule($ruleId, $viewId, $columnIds);
			} else {
				$this->ruleColMapper->insertForRule($ruleId, $viewId, $columnIds);
			}
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to sync formatting junction index', ['exception' => $e]);
			throw new InternalError('Could not index the formatting rule: ' . $e->getMessage());
		}
	}

	/**
	 * @param string[] $ruleIds
	 * @throws InternalError
	 */
	private function deleteJunctionRows(array $ruleIds): void {
		try {
			foreach ($ruleIds as $ruleId) {
				$this->ruleColMapper->deleteByRule($ruleId);
			}
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('Failed to drop formatting junction rows', ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}
	}

	private static function targetColOf(array $ruleSet): ?int {
		return isset($ruleSet['targetCol']) ? (int)$ruleSet['targetCol'] : null;
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

	private function revalidateBrokenRules(View $view, array &$formatting): void {
		if (!$this->hasBrokenRule($formatting)) {
			return;
		}

		[$typeMap, $optionIdMap] = $this->buildColumnMaps($view->getTableId());

		foreach ($formatting as &$ruleSet) {
			if ($ruleSet['broken'] ?? false) {
				$paintsAColumn = ($ruleSet['targetType'] ?? null) === 'column';
				$targetCol = isset($ruleSet['targetCol']) ? (int)$ruleSet['targetCol'] : null;
				$targetResolves = $targetCol !== null
					&& (Column::isValidMetaTypeId($targetCol) || isset($typeMap[$targetCol]));
				if (!$paintsAColumn || $targetResolves) {
					$ruleSet['broken'] = false;
				}
			}

			if (!isset($ruleSet['rules']) || !is_array($ruleSet['rules'])) {
				continue;
			}
			foreach ($ruleSet['rules'] as &$rule) {
				if (!($rule['broken'] ?? false)) {
					continue;
				}
				if ($this->allConditionsValid($rule['condition'] ?? [], $typeMap, $optionIdMap)) {
					$rule['broken'] = false;
				}
			}
			unset($rule);
		}
		unset($ruleSet);
	}

	private function hasBrokenRule(array $formatting): bool {
		foreach ($formatting as $ruleSet) {
			if ($ruleSet['broken'] ?? false) {
				return true;
			}
			foreach ($ruleSet['rules'] ?? [] as $rule) {
				if ($rule['broken'] ?? false) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array<int, string> $typeMap
	 * @param array<int, list<int>> $optionIdMap
	 */
	private function allConditionsValid(array $conditionSet, array $typeMap, array $optionIdMap): bool {
		foreach ($conditionSet['groups'] ?? [] as $group) {
			foreach ($group['conditions'] ?? [] as $condition) {
				$columnId = (int)($condition['columnId'] ?? 0);
				if (Column::isValidMetaTypeId($columnId)) {
					continue;
				}
				if (!isset($typeMap[$columnId]) || $typeMap[$columnId] !== ($condition['columnType'] ?? null)) {
					return false;
				}
				if (!self::selectionValuesResolve($condition['value'] ?? null, $optionIdMap[$columnId] ?? [])) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * @param list<int> $optionIds the column's live selection option ids
	 */
	private static function selectionValuesResolve(mixed $value, array $optionIds): bool {
		foreach (is_array($value) ? $value : [$value] as $entry) {
			if (is_array($entry)) {
				if (isset($entry['id']) && !in_array((int)$entry['id'], $optionIds, true)) {
					return false;
				}
				continue;
			}
			$optionId = SelectionReference::optionId($entry);
			if ($optionId === null) {
				continue;
			}
			if (!in_array($optionId, $optionIds, true)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array{0: array<int, string>, 1: array<int, list<int>>} the columns' types and
	 *                                                                their live selection option ids, both keyed by column id
	 */
	private function buildColumnMaps(int $tableId): array {
		try {
			$columns = $this->columnMapper->findAllByTable($tableId);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return [[], []];
		}
		$typeMap = [];
		$optionIdMap = [];
		foreach ($columns as $column) {
			$subtype = $column->getSubtype();
			$typeMap[$column->getId()] = $subtype ? $column->getType() . '-' . $subtype : $column->getType();
			$optionIdMap[$column->getId()] = array_map(intval(...), array_column($column->getSelectionOptionsArray() ?? [], 'id'));
		}
		return [$typeMap, $optionIdMap];
	}

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
	private static function findIndexById(array $items, string $id): int {
		foreach ($items as $index => $item) {
			if (($item['id'] ?? null) === $id) {
				return $index;
			}
		}
		return -1;
	}

	private static function findRule(array $rules, string $ruleId): ?array {
		$index = self::findIndexById($rules, $ruleId);
		return $index === -1 ? null : $rules[$index];
	}

	/**
	 * @param string[] $ruleIds
	 * @param string[]|null $magicValues
	 * @return bool whether anything changed
	 */
	private function markRulesBroken(array &$formatting, array $ruleIds, ?array $magicValues = null, ?int $deletedColumnId = null): bool {
		$ruleIdSet = array_flip($ruleIds);
		$changed = false;

		foreach ($formatting as &$ruleSet) {
			if ($deletedColumnId !== null
				&& ($ruleSet['targetType'] ?? null) === 'column'
				&& isset($ruleSet['targetCol'])
				&& (int)$ruleSet['targetCol'] === $deletedColumnId
				&& !($ruleSet['broken'] ?? false)
			) {
				$ruleSet['broken'] = true;
				$changed = true;
			}

			if (!isset($ruleSet['rules']) || !is_array($ruleSet['rules'])) {
				continue;
			}
			foreach ($ruleSet['rules'] as &$rule) {
				if (!isset($ruleIdSet[$rule['id'] ?? ''])) {
					continue;
				}
				if ($magicValues !== null && !$this->ruleUsesValue($rule, $magicValues)) {
					continue;
				}
				$rule['broken'] = true;
				$changed = true;
			}
			unset($rule);
		}
		unset($ruleSet);

		return $changed;
	}

	/** @param string[] $needles */
	private function ruleUsesValue(array $rule, array $needles): bool {
		foreach ($rule['condition']['groups'] ?? [] as $group) {
			foreach ($group['conditions'] ?? [] as $condition) {
				$value = $condition['value'] ?? null;
				if (!is_array($value)) {
					if (is_string($value) && in_array($value, $needles, true)) {
						return true;
					}
					continue;
				}
				foreach ($value as $entry) {
					if (is_string($entry) && in_array($entry, $needles, true)) {
						return true;
					}
					if (is_array($entry)
						&& isset($entry['id'])
						&& in_array(SelectionReference::forOptionId((int)$entry['id']), $needles, true)
					) {
						return true;
					}
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
		return Uuid::v7()->toRfc4122();
	}
}
