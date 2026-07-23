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

		foreach ($column->getSelectionOptionsCollection() as $option) {
			if ($option->key() === $intValue) {
				return json_encode((string)$option->key());
			}
		}

		return '';
	}

	public function parseDisplayValue($value, Column $column): string {
		foreach ($column->getSelectionOptionsCollection() as $option) {
			if ($option->label() === $value) {
				return json_encode($option->key());
			}
		}

		return '';
	}

	/**
	 * @param mixed $value (array|string|null)
	 * @param Column $column
	 * @return bool
	 */
	public function canBeParsed(mixed $value, Column $column): bool {
		if ($value === null) {
			return true;
		}

		$intValue = (int)$value;
		if (!is_numeric($value) || $intValue != $value) {
			return false;
		}

		foreach ($column->getSelectionOptionsCollection() as $option) {
			if ($option->key() === $intValue) {
				return true;
			}
		}

		return false;
	}

	public function canBeParsedDisplayValue($value, Column $column): bool {
		if ($value === null) {
			return true;
		}

		foreach ($column->getSelectionOptionsCollection() as $option) {
			if ($option->label() === $value) {
				return true;
			}
		}

		return false;
	}
}
