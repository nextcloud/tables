<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class SelectionBusiness extends SuperBusiness {

	/**
	 * @param mixed $value (array|string|null)
	 * @param Column $column
	 * @return string
	 */
	public function parseValue($value, Column $column): string {
		$intValue = (int)$value;
		if (!is_numeric($value) || $intValue != $value) {
			return '';
		}

		foreach ($column->getSelectionOptionsArray() as $option) {
			if ($option['id'] === $intValue) {
				return json_encode($option['id']);
			}
		}

		return '';
	}

	public function parseDisplayValue($value, Column $column): string {
		foreach ($column->getSelectionOptionsArray() as $option) {
			if ($option['label'] === $value) {
				return json_encode($option['id']);
			}
		}

		return '';
	}

	/**
	 * @param mixed $value (array|string|null)
	 * @param Column $column
	 * @return bool
	 */
	public function canBeParsed($value, Column $column): bool {
		if ($value === null) {
			return true;
		}

		$intValue = (int)$value;
		if (!is_numeric($value) || $intValue != $value) {
			return false;
		}

		foreach ($column->getSelectionOptionsArray() as $option) {
			if ($option['id'] === $intValue) {
				return true;
			}
		}

		return false;
	}

	public function canBeParsedDisplayValue($value, Column $column): bool {
		if ($value === null) {
			return true;
		}

		foreach ($column->getSelectionOptionsArray() as $option) {
			if ($option['label'] === $value) {
				return true;
			}
		}

		return false;
	}
}
