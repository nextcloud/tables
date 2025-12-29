<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCA\Tables\Constants\UsergroupType;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\GroupHelper;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;

/** @template-extends RowCellMapperSuper<RowCellUsergroup, array, array> */
class RowCellUsergroupMapper extends RowCellMapperSuper {
	use RowCellBulkFetchTrait;

	protected string $table = 'tables_row_cells_usergroup';

	public function __construct(
		IDBConnection $db,
		private IUserManager $userManager,
		private CircleHelper $circleHelper,
		private GroupHelper $groupHelper,
		protected IUserSession $userSession,
	) {
		parent::__construct($db, $this->table, RowCellUsergroup::class);
	}

	public function filterValueToQueryParam(Column $column, mixed $value): mixed {
		return $value;
	}

	public function applyDataToEntity(Column $column, RowCellSuper $cell, $data): void {
		if (!RowCellUsergroup::verifyUserGroupArray($data)) {
			throw new \InvalidArgumentException('Provided value is not valid user group data');
		}

		$cell->setValueWrapper($data);
	}

	public function formatRowData(Column $column, array $row) {
		$value = $row['value'];
		$valueType = (int)$row['value_type'];
		$displayName = $value;
		if ($valueType === UsergroupType::USER) {
			$displayName = $this->userManager->getDisplayName($value) ?? $value;
		} elseif ($valueType === UsergroupType::CIRCLE) {
			$displayName = $this->circleHelper->getCircleDisplayName($value, ($this->userSession->getUser()?->getUID() ?: '')) ?: $value;
		} elseif ($valueType === UsergroupType::GROUP) {
			$displayName = $this->groupHelper->getGroupDisplayName($value) ?: $value;
		}
		return [
			'id' => $value,
			'type' => $valueType,
			'displayName' => $displayName,
		];
	}

	public function toArray(RowCellSuper $cell): array {
		return [
			'value' => $cell->getValue(),
			'value_type' => $cell->getValueType(),
		];
	}

	public function hasMultipleValues(): bool {
		return true;
	}
}
