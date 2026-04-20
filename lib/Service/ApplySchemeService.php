<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Model\ViewUpdateInput;
use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class ApplySchemeService extends SuperService {
	use TTransactional;

	private const MAX_COLUMNS = 500;
	private const MAX_VIEWS = 100;

	private const ALLOWED_COLUMN_FIELDS = [
		'title', 'description', 'mandatory', 'textDefault', 'textAllowedPattern',
		'textMaxLength', 'textUnique', 'numberDefault', 'numberMin', 'numberMax',
		'numberDecimals', 'numberPrefix', 'numberSuffix', 'selectionOptions',
		'selectionDefault', 'datetimeDefault', 'usergroupDefault',
		'usergroupMultipleItems', 'usergroupSelectUsers', 'usergroupSelectGroups',
		'usergroupSelectTeams', 'showUserStatus', 'customSettings',
	];

	private const ALLOWED_VIEW_FIELDS = [
		'emoji', 'description', 'filter', 'sort', 'columns', 'columnSettings',
	];

	public function __construct(
		LoggerInterface $logger,
		?string $userId,
		PermissionsService $permissionsService,
		private TableService $tableService,
		private ColumnService $columnService,
		private ViewService $viewService,
		private IDBConnection $dbc,
	) {
		parent::__construct($logger, $userId, $permissionsService);
	}

	/**
	 * Apply selected structural changes from an incoming scheme to an existing table.
	 *
	 * Runs entirely inside a DB transaction. On any exception: rolls back and rethrows
	 * an InternalError that names the failed step.
	 *
	 * @param int $tableId Target table ID
	 * @param array $scheme Full incoming scheme (parsed JSON)
	 * @param array $selection Selection payload describing which changes to apply
	 * @return array Full updated scheme (jsonSerialize of TableScheme)
	 * @throws BadRequestError
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function apply(int $tableId, array $scheme, array $selection): array {
		$this->validateSchemeStructure($scheme);
		$this->enforceArraySizeLimits($scheme, $selection);
		$this->enforceFieldWhitelists($selection);
		$this->validateTextAllowedPatterns($scheme, $selection);

		// Cast all incoming IDs to int (SH-6)
		$columnsAdd = array_map('intval', $selection['columnsAdd'] ?? []);
		$columnsDelete = array_map('intval', $selection['columnsDelete'] ?? []);
		$columnsUpdate = [];
		foreach ($selection['columnsUpdate'] ?? [] as $rawId => $fields) {
			$columnsUpdate[(int)$rawId] = $fields;
		}
		$viewsAdd = $selection['viewsAdd'] ?? [];
		$viewsUpdate = $selection['viewsUpdate'] ?? [];
		$tableMeta = $selection['tableMeta'] ?? [];

		// Index source columns by ID for fast lookup
		$sourceColumnsById = [];
		foreach ($scheme['columns'] as $col) {
			$sourceColumnsById[(int)$col['id']] = $col;
		}

		// Index source views by title for fast lookup
		$sourceViewsByTitle = [];
		foreach ($scheme['views'] ?? [] as $view) {
			$sourceViewsByTitle[$view['title']] = $view;
		}

		$failedStep = 'unknown';
		try {
			return $this->atomic(function () use (
				$tableId, $scheme, $selection,
				$columnsAdd, $columnsDelete, $columnsUpdate,
				$viewsAdd, $viewsUpdate, $tableMeta,
				$sourceColumnsById, $sourceViewsByTitle,
				&$failedStep
			): array {
				// Load owned columns and views for IDOR checks
				$ownedColumns = $this->columnService->findAllByTable($tableId, $this->userId);
				$ownedColumnIds = array_map(fn ($c) => $c->getId(), $ownedColumns);

				$table = $this->tableService->find($tableId, true, $this->userId);
				$ownedViews = $this->viewService->findAll($table, $this->userId);
				$ownedViewsByTitle = [];
				foreach ($ownedViews as $view) {
					$ownedViewsByTitle[$view->getTitle()] = $view;
				}

				// 1. Table meta
				if (!empty($tableMeta)) {
					$failedStep = 'update table metadata';
					$title = in_array('title', $tableMeta, true) ? ($scheme['title'] ?? null) : null;
					$emoji = in_array('emoji', $tableMeta, true) ? ($scheme['emoji'] ?? null) : null;
					$description = in_array('description', $tableMeta, true) ? ($scheme['description'] ?? null) : null;
					$this->tableService->update($tableId, $title, $emoji, $description, null, $this->userId);
				}

				// 2. Add columns — track new sourceId → targetId mapping
				$newColMap = [];
				foreach ($columnsAdd as $sourceId) {
					if (!isset($sourceColumnsById[$sourceId])) {
						throw new BadRequestError("Source column ID {$sourceId} not found in scheme.");
					}
					$srcCol = $sourceColumnsById[$sourceId];
					$failedStep = "create column '{$srcCol['title']}'";
					$dto = $this->buildColumnDto($srcCol);
					$newCol = $this->columnService->create($this->userId, $tableId, null, $dto);
					$newColMap[$sourceId] = $newCol->getId();
				}

				// 3. Update columns
				foreach ($columnsUpdate as $targetId => $fields) {
					if (!in_array($targetId, $ownedColumnIds, true)) {
						throw new PermissionError("Column ID {$targetId} does not belong to table {$tableId}.");
					}
					$srcCol = $this->findSourceColumnForTarget($targetId, $ownedColumns, $scheme['columns']);
					if ($srcCol === null) {
						throw new BadRequestError("Cannot find source column data for target column ID {$targetId}.");
					}
					$failedStep = "update column ID {$targetId}";
					$dto = $this->buildPartialColumnDto($srcCol, $fields);
					$this->columnService->update($targetId, $this->userId, $dto);
				}

				// 4. Delete columns
				foreach ($columnsDelete as $targetId) {
					if (!in_array($targetId, $ownedColumnIds, true)) {
						throw new PermissionError("Column ID {$targetId} does not belong to table {$tableId}.");
					}
					$failedStep = "delete column ID {$targetId}";
					$this->columnService->delete($targetId, false, $this->userId);
				}

				// Build combined column map (pre-existing matches + newly created)
				$combinedColMap = $newColMap;
				foreach ($scheme['columns'] as $srcCol) {
					foreach ($ownedColumns as $tgtCol) {
						if (strtolower($srcCol['title']) === strtolower($tgtCol->getTitle())
							&& $srcCol['type'] === $tgtCol->getType()
						) {
							$combinedColMap[(int)$srcCol['id']] = $tgtCol->getId();
						}
					}
				}

				// 5. Add views
				foreach ($viewsAdd as $viewTitle) {
					if (!isset($sourceViewsByTitle[$viewTitle])) {
						throw new BadRequestError("Source view '{$viewTitle}' not found in scheme.");
					}
					$srcView = $sourceViewsByTitle[$viewTitle];
					$failedStep = "create view '{$viewTitle}'";
					$remappedView = $this->remapViewColumnIds($srcView, $combinedColMap, $viewTitle);
					$newView = $this->viewService->create($viewTitle, $srcView['emoji'] ?? null, $table, $this->userId);
					$updateData = $this->buildViewUpdateInput($remappedView);
					$this->viewService->update($newView->getId(), $updateData, $this->userId);
				}

				// 6. Update views
				foreach ($viewsUpdate as $viewTitle => $fields) {
					if (!isset($ownedViewsByTitle[$viewTitle])) {
						throw new NotFoundError("View '{$viewTitle}' not found in table {$tableId}.");
					}
					$view = $ownedViewsByTitle[$viewTitle];
					if ($view->getTableId() !== $tableId) {
						throw new NotFoundError("View '{$viewTitle}' does not belong to table {$tableId}.");
					}
					if (!isset($sourceViewsByTitle[$viewTitle])) {
						throw new BadRequestError("Source view '{$viewTitle}' not found in scheme.");
					}
					$srcView = $sourceViewsByTitle[$viewTitle];
					$failedStep = "update view '{$viewTitle}'";
					// Only apply selected fields
					$updatePayload = $this->buildPartialViewPayload($srcView, $fields);
					$this->viewService->update($view->getId(), ViewUpdateInput::fromInputArray($updatePayload), $this->userId);
				}

				// Reload and return full updated scheme
				return $this->tableService->getScheme($tableId, $this->userId)->jsonSerialize();
			}, $this->dbc);
		} catch (BadRequestError|PermissionError|NotFoundError $e) {
			throw $e;
		} catch (\Exception $e) {
			$this->logger->error('ApplySchemeService failed at step "' . $failedStep . '": ' . $e->getMessage(), ['exception' => $e]);
			throw new InternalError('Failed at step: ' . $failedStep . '. ' . $e->getMessage());
		}
	}

	/**
	 * @throws BadRequestError
	 */
	private function validateSchemeStructure(array $scheme): void {
		if (!isset($scheme['columns'], $scheme['views'])) {
			throw new BadRequestError('Invalid scheme structure: missing required keys "columns" or "views".');
		}
		if (!is_array($scheme['columns']) || !is_array($scheme['views'])) {
			throw new BadRequestError('Invalid scheme structure: "columns" and "views" must be arrays.');
		}
		foreach ($scheme['columns'] as $col) {
			if (!is_array($col)
				|| !isset($col['id'], $col['title'], $col['type'])
				|| !is_int($col['id'])
				|| !is_string($col['title'])
				|| !is_string($col['type'])
			) {
				throw new BadRequestError('Invalid scheme structure: each column must have integer "id", string "title" and string "type".');
			}
		}
	}

	/**
	 * @throws BadRequestError
	 */
	private function enforceArraySizeLimits(array $scheme, array $selection): void {
		if (count($scheme['columns']) > self::MAX_COLUMNS) {
			throw new BadRequestError('Payload exceeds maximum allowed size: "columns" must not exceed ' . self::MAX_COLUMNS . ' entries.');
		}
		if (count($scheme['views'] ?? []) > self::MAX_VIEWS) {
			throw new BadRequestError('Payload exceeds maximum allowed size: "views" must not exceed ' . self::MAX_VIEWS . ' entries.');
		}
		if (count($selection['columnsAdd'] ?? []) > self::MAX_COLUMNS) {
			throw new BadRequestError('Payload exceeds maximum allowed size: "columnsAdd" must not exceed ' . self::MAX_COLUMNS . ' entries.');
		}
		if (count($selection['columnsUpdate'] ?? []) > self::MAX_COLUMNS) {
			throw new BadRequestError('Payload exceeds maximum allowed size: "columnsUpdate" must not exceed ' . self::MAX_COLUMNS . ' entries.');
		}
		if (count($selection['columnsDelete'] ?? []) > self::MAX_COLUMNS) {
			throw new BadRequestError('Payload exceeds maximum allowed size: "columnsDelete" must not exceed ' . self::MAX_COLUMNS . ' entries.');
		}
		if (count($selection['viewsAdd'] ?? []) > self::MAX_VIEWS) {
			throw new BadRequestError('Payload exceeds maximum allowed size: "viewsAdd" must not exceed ' . self::MAX_VIEWS . ' entries.');
		}
		if (count($selection['viewsUpdate'] ?? []) > self::MAX_VIEWS) {
			throw new BadRequestError('Payload exceeds maximum allowed size: "viewsUpdate" must not exceed ' . self::MAX_VIEWS . ' entries.');
		}
	}

	/**
	 * @throws BadRequestError
	 */
	private function enforceFieldWhitelists(array $selection): void {
		foreach ($selection['columnsUpdate'] ?? [] as $rawId => $fields) {
			foreach ((array)$fields as $field) {
				if (!in_array($field, self::ALLOWED_COLUMN_FIELDS, true)) {
					throw new BadRequestError("Field \"{$field}\" is not allowed in columnsUpdate.");
				}
			}
		}
		foreach ($selection['viewsUpdate'] ?? [] as $viewTitle => $fields) {
			foreach ((array)$fields as $field) {
				if (!in_array($field, self::ALLOWED_VIEW_FIELDS, true)) {
					throw new BadRequestError("Field \"{$field}\" is not allowed in viewsUpdate.");
				}
			}
		}
	}

	/**
	 * Validate textAllowedPattern for any column being added or updated (SH-1).
	 * @throws BadRequestError
	 */
	private function validateTextAllowedPatterns(array $scheme, array $selection): void {
		$sourceColumnsById = [];
		foreach ($scheme['columns'] as $col) {
			$sourceColumnsById[(int)$col['id']] = $col;
		}

		$idsToCheck = array_merge(
			array_map('intval', $selection['columnsAdd'] ?? []),
			array_keys(array_map('intval', array_keys($selection['columnsUpdate'] ?? [])))
		);
		// Also check update targets — we need source columns for those
		foreach ($selection['columnsUpdate'] ?? [] as $rawId => $fields) {
			if (in_array('textAllowedPattern', (array)$fields, true)) {
				// find source column matching this target by iterating scheme
				foreach ($scheme['columns'] as $col) {
					if (!empty($col['textAllowedPattern'])) {
						$this->validateSinglePattern($col['textAllowedPattern']);
					}
				}
			}
		}

		foreach (array_map('intval', $selection['columnsAdd'] ?? []) as $sourceId) {
			if (isset($sourceColumnsById[$sourceId]['textAllowedPattern'])
				&& $sourceColumnsById[$sourceId]['textAllowedPattern'] !== null
				&& $sourceColumnsById[$sourceId]['textAllowedPattern'] !== ''
			) {
				$this->validateSinglePattern($sourceColumnsById[$sourceId]['textAllowedPattern']);
			}
		}
	}

	/**
	 * @throws BadRequestError
	 */
	private function validateSinglePattern(string $pattern): void {
		if (strlen($pattern) > 250) {
			throw new BadRequestError('Column "textAllowedPattern" must not exceed 250 characters.');
		}
		$result = @preg_match($pattern, '');
		if ($result === false) {
			throw new BadRequestError('Column "textAllowedPattern" contains an invalid or unsafe regex pattern.');
		}
	}

	private function buildColumnDto(array $srcCol): ColumnDto {
		$selectionOptions = $srcCol['selectionOptions'] ?? null;
		if (is_array($selectionOptions)) {
			$selectionOptions = json_encode($selectionOptions);
		}
		$usergroupDefault = $srcCol['usergroupDefault'] ?? null;
		if (is_array($usergroupDefault)) {
			$usergroupDefault = $usergroupDefault[0] ?? '';
		}
		$customSettings = $srcCol['customSettings'] ?? null;
		if (is_array($customSettings)) {
			$customSettings = json_encode($customSettings);
		}
		return new ColumnDto(
			title: $srcCol['title'] ?? null,
			type: $srcCol['type'] ?? null,
			subtype: $srcCol['subtype'] ?? null,
			mandatory: $srcCol['mandatory'] ?? null,
			description: $srcCol['description'] ?? null,
			textDefault: $srcCol['textDefault'] ?? null,
			textAllowedPattern: $srcCol['textAllowedPattern'] ?? null,
			textMaxLength: isset($srcCol['textMaxLength']) ? (int)$srcCol['textMaxLength'] : null,
			textUnique: $srcCol['textUnique'] ?? null,
			numberDefault: isset($srcCol['numberDefault']) ? (float)$srcCol['numberDefault'] : null,
			numberMin: isset($srcCol['numberMin']) ? (float)$srcCol['numberMin'] : null,
			numberMax: isset($srcCol['numberMax']) ? (float)$srcCol['numberMax'] : null,
			numberDecimals: isset($srcCol['numberDecimals']) ? (int)$srcCol['numberDecimals'] : null,
			numberPrefix: $srcCol['numberPrefix'] ?? null,
			numberSuffix: $srcCol['numberSuffix'] ?? null,
			selectionOptions: $selectionOptions === [] ? '' : $selectionOptions,
			selectionDefault: $srcCol['selectionDefault'] ?? null,
			datetimeDefault: $srcCol['datetimeDefault'] ?? null,
			usergroupDefault: $usergroupDefault,
			usergroupMultipleItems: $srcCol['usergroupMultipleItems'] ?? null,
			usergroupSelectUsers: $srcCol['usergroupSelectUsers'] ?? null,
			usergroupSelectGroups: $srcCol['usergroupSelectGroups'] ?? null,
			usergroupSelectTeams: $srcCol['usergroupSelectTeams'] ?? null,
			showUserStatus: $srcCol['showUserStatus'] ?? null,
			customSettings: $customSettings,
		);
	}

	/**
	 * Build a ColumnDto containing only the selected fields (for updates).
	 */
	private function buildPartialColumnDto(array $srcCol, array $fields): ColumnDto {
		$data = [];
		foreach ($fields as $field) {
			if (array_key_exists($field, $srcCol)) {
				$data[$field] = $srcCol[$field];
			}
		}
		// Normalize selectionOptions
		if (isset($data['selectionOptions']) && is_array($data['selectionOptions'])) {
			$data['selectionOptions'] = json_encode($data['selectionOptions']);
		}
		if (isset($data['usergroupDefault']) && is_array($data['usergroupDefault'])) {
			$data['usergroupDefault'] = $data['usergroupDefault'][0] ?? '';
		}
		if (isset($data['customSettings']) && is_array($data['customSettings'])) {
			$data['customSettings'] = json_encode($data['customSettings']);
		}
		return ColumnDto::createFromArray($data);
	}

	/**
	 * Remap source column IDs in a view's filter, sort, and columnSettings to target IDs.
	 * Does NOT remap @selection-id-{id} tokens (SH-8).
	 *
	 * @throws BadRequestError
	 */
	private function remapViewColumnIds(array $srcView, array $colMap, string $viewTitle): array {
		$remapped = $srcView;

		// Remap filter column IDs
		if (isset($remapped['filter']) && is_array($remapped['filter'])) {
			foreach ($remapped['filter'] as &$filterGroup) {
				foreach ($filterGroup as &$filter) {
					if (isset($filter['columnId']) && (int)$filter['columnId'] > 0) {
						$srcId = (int)$filter['columnId'];
						if (!array_key_exists($srcId, $colMap)) {
							throw new BadRequestError("View '{$viewTitle}' references unknown source column ID {$srcId}.");
						}
						$filter['columnId'] = $colMap[$srcId];
					}
				}
			}
		}

		// Remap sort column IDs
		if (isset($remapped['sort']) && is_array($remapped['sort'])) {
			foreach ($remapped['sort'] as &$sort) {
				if (isset($sort['columnId']) && (int)$sort['columnId'] > 0) {
					$srcId = (int)$sort['columnId'];
					if (!array_key_exists($srcId, $colMap)) {
						throw new BadRequestError("View '{$viewTitle}' references unknown source column ID {$srcId}.");
					}
					$sort['columnId'] = $colMap[$srcId];
				}
			}
		}

		// Remap columnSettings column IDs
		if (isset($remapped['columnSettings']) && is_array($remapped['columnSettings'])) {
			foreach ($remapped['columnSettings'] as &$cs) {
				if (isset($cs['columnId']) && (int)$cs['columnId'] > 0) {
					$srcId = (int)$cs['columnId'];
					if (!array_key_exists($srcId, $colMap)) {
						throw new BadRequestError("View '{$viewTitle}' references unknown source column ID {$srcId}.");
					}
					$cs['columnId'] = $colMap[$srcId];
				}
			}
		} elseif (isset($remapped['columns']) && is_array($remapped['columns'])) {
			foreach ($remapped['columns'] as &$colId) {
				if ((int)$colId > 0) {
					$srcId = (int)$colId;
					if (!array_key_exists($srcId, $colMap)) {
						throw new BadRequestError("View '{$viewTitle}' references unknown source column ID {$srcId}.");
					}
					$colId = $colMap[$srcId];
				}
			}
		}

		return $remapped;
	}

	private function buildViewUpdateInput(array $srcView): ViewUpdateInput {
		$payload = [];
		foreach (['emoji', 'description', 'filter', 'sort', 'columnSettings', 'columns'] as $field) {
			if (array_key_exists($field, $srcView)) {
				$payload[$field] = $srcView[$field];
			}
		}
		return ViewUpdateInput::fromInputArray($payload);
	}

	/**
	 * Build a partial view update payload containing only the selected fields.
	 */
	private function buildPartialViewPayload(array $srcView, array $fields): array {
		$payload = [];
		foreach ($fields as $field) {
			if (array_key_exists($field, $srcView)) {
				$payload[$field] = $srcView[$field];
			}
		}
		return $payload;
	}

	/**
	 * Find the source column data that corresponds to the given target column ID.
	 * Matches by title+type (same logic as StructureDiffService).
	 *
	 * @param \OCA\Tables\Db\Column[] $ownedColumns
	 */
	private function findSourceColumnForTarget(int $targetId, array $ownedColumns, array $sourceColumns): ?array {
		// Find the owned column object
		$ownedCol = null;
		foreach ($ownedColumns as $col) {
			if ($col->getId() === $targetId) {
				$ownedCol = $col;
				break;
			}
		}
		if ($ownedCol === null) {
			return null;
		}
		// Find matching source column by title (case-insensitive) + type
		foreach ($sourceColumns as $srcCol) {
			if (strtolower($srcCol['title']) === strtolower($ownedCol->getTitle())
				&& $srcCol['type'] === $ownedCol->getType()
			) {
				return $srcCol;
			}
		}
		return null;
	}
}
