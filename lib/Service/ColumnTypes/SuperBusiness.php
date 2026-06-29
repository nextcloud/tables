<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use DateTime;
use OCA\Tables\Db\Column;
use Psr\Log\LoggerInterface;

class SuperBusiness implements IColumnTypeBusiness {

	public function __construct(
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * @param mixed $value
	 * @param Column $column
	 *
	 * @return false|string
	 */
	public function parseValue($value, Column $column): string|false {
		return json_encode($value);
	}

	/**
	 * @return false|string
	 */
	public function parseDisplayValue($value, Column $column): string|false {
		return $this->parseValue($value, $column);
	}

	/**
	 * @param mixed $value
	 * @param Column $column
	 * @return bool
	 */
	public function canBeParsed($value, Column $column): bool {
		return true;
	}

	public function validateValue(mixed $value, Column $column, string $userId, int $tableId, ?int $rowId): void {
		// override this method in the child class when needed
	}

	public function canBeParsedDisplayValue($value, Column $column): bool {
		return $this->canBeParsed($value, $column);
	}

	protected function isValidDate(string $dateString, string $format): bool {
		try {
			$dateTime = new DateTime($dateString);
			$newString = $dateTime->format($format);
			return $newString === $dateString;
		} catch (\Exception $e) {
			$this->logger->warning('Could not validate date: ' . $e->getMessage() . ' Returning false.');
			return false;
		}
	}
}
