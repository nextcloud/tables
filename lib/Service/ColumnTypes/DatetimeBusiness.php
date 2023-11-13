<?php

namespace OCA\Tables\Service\ColumnTypes;

use DateTime;
use Exception;
use OCA\Tables\Db\Column;

class DatetimeBusiness extends SuperBusiness implements IColumnTypeBusiness {

	/**
	 * @param mixed $value (string|null)
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		$allowedFormats = [\DateTimeInterface::ATOM, 'Y-m-d H:i'];
		$newDateTime = '';

		try {
			$dateTime = new DateTime($value);
			foreach ($allowedFormats as $format) {
				$tmp = $dateTime->format($format);
				if($value === $tmp) {
					$newDateTime = $dateTime->format('Y-m-d H:i');
				}
			}
		} catch (Exception $e) {
			$this->logger->debug('Could not parse format for datetime value', ['exception' => $e]);
		}

		return json_encode($newDateTime !== '' ? $newDateTime : '');
	}

	/**
	 * @param mixed $value (string|null)
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		try {
			new DateTime($value);
		} catch (Exception $e) {
			return false;
		}
		return true;
	}
}
