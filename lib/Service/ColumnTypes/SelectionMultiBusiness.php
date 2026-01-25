<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class SelectionMultiBusiness extends SuperBusiness {

	private array $options = [];

	/**
	 * @param mixed $value (array|string|null)
	 * @param Column $column
	 * @return string
	 */
	public function parseValue($value, Column $column): string {
		if ($value === null) {
			return json_encode([]);
		}

		$this->options = $column->getSelectionOptionsArray();

		$wasString = false;
		if (is_string($value)) {
			$value = array_map('trim', explode(',', $value));
			$wasString = true;
		}

		$result = [];
		foreach ($value as $wantedValue) {
			if (!$wasString) {
				$wantedValue = (int)$wantedValue;
			}
			if ($this->getOptionIdForValue($wantedValue) !== null) {
				$result[] = $this->getOptionIdForValue($wantedValue);
			}
		}
		sort($result, SORT_NUMERIC);
		return json_encode($result);
	}

	/**
	 * @param int|string|null $value int assume as option ID, string assumes a label
	 * @return int|null return always the option ID or null
	 */
	private function getOptionIdForValue($value): ?int {
		if ($value === null) {
			return null;
		}

		foreach ($this->options as $option) {
			if (is_int($value)) {
				if ($option['id'] === $value) {
					return $option['id'];
				}
			} else {
				if ($option['label'] === $value) {
					return $option['id'];
				}
			}
		}
		return null;
	}

	/**
	 * @param mixed $value (int[]|string|null) Array of option IDs or string with comma seperated labels
	 * @param Column $column
	 * @return bool
	 */
	public function canBeParsed($value, Column $column): bool {
		if ($value === null) {
			return true;
		}

		$this->options = $column->getSelectionOptionsArray();

		$wasString = false;
		if (is_string($value)) {
			$value = array_map('trim', explode(',', $value));
			$wasString = true;
		}

		foreach ($value as $wantedValue) {
			if (!$wasString) {
				$wantedValue = (int)$wantedValue;
			}
			if ($this->getOptionIdForValue((int)$wantedValue) === null) {
				return false;
			}
		}
		return true;
	}
}
