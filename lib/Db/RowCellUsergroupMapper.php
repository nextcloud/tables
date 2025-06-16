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

	public function formatEntity(Column $column, RowCellSuper $cell) {
		$displayName = $cell->getValue();
		if ($cell->getValueType() === UsergroupType::USER) {
			$displayName = $this->userManager->getDisplayName($cell->getValue()) ?? $cell->getValue();
		} elseif ($cell->getValueType() === UsergroupType::CIRCLE) {
			$displayName = $this->circleHelper->getCircleDisplayName($cell->getValue(), ($this->userSession->getUser()?->getUID() ?: '')) ?: $cell->getValue();
		} elseif ($cell->getValueType() === UsergroupType::GROUP) {
			$displayName = $this->groupHelper->getGroupDisplayName($cell->getValue()) ?: $cell->getValue();
		}
		return [
			'id' => $cell->getValue(),
			'type' => $cell->getValueType(),
			'displayName' => $displayName,
		];
	}

	public function hasMultipleValues(): bool {
		return true;
	}
}
