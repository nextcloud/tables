<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use DateTime;
use OCA\Tables\Db\Column;
use Psr\Log\LoggerInterface;

class SuperBusiness {

	protected LoggerInterface $logger;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	/**
	 * @param mixed $value
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		return json_encode($value);
	}

	public function parseDisplayValue($value, ?Column $column = null): string {
		return $this->parseValue($value, $column);
	}

	/**
	 * @param mixed $value
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		return true;
	}

	public function validateValue(mixed $value, Column $column, string $userId, int $tableId, ?int $rowId): void {
		// override this method in the child class when needed
	}

	public function canBeParsedDisplayValue($value, ?Column $column = null): bool {
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
