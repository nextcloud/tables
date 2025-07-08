<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class SelectionBusiness extends SuperBusiness implements IColumnTypeBusiness {

	/**
	 * @param mixed $value (array|string|null)
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		if (!$column) {
			$this->logger->warning('No column given, but expected on ' . __FUNCTION__ . ' within ' . __CLASS__, ['exception' => new \Exception()]);
			return '';
		}

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

	public function parseDisplayValue($value, ?Column $column = null): string {
		if (!$column) {
			$this->logger->warning('No column given, but expected on ' . __FUNCTION__ . ' within ' . __CLASS__, ['exception' => new \Exception()]);
			return '';
		}

		foreach ($column->getSelectionOptionsArray() as $option) {
			if ($option['label'] === $value) {
				return json_encode($option['id']);
			}
		}

		return '';
	}

	/**
	 * @param mixed $value (array|string|null)
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		if (!$column) {
			$this->logger->warning('No column given, but expected on ' . __FUNCTION__ . ' within ' . __CLASS__, ['exception' => new \Exception()]);
			return false;
		}
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

	public function canBeParsedDisplayValue($value, ?Column $column = null): bool {
		if (!$column) {
			$this->logger->warning('No column given, but expected on ' . __FUNCTION__ . ' within ' . __CLASS__, ['exception' => new \Exception()]);
			return false;
		}
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
