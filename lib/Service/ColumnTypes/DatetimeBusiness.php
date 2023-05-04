<?php

namespace OCA\Tables\Service\ColumnTypes;

use DateTime;
use OCA\Tables\Db\Column;

class DatetimeBusiness extends SuperBusiness implements IColumnTypeBusiness {

	/**
	 * @throws \Exception
	 */
	public function parseValue(string $value, ?Column $column = null): string {
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
		} catch (\Exception $e) {
			$this->logger->debug('Could not parse format for datetime value', ['exception' => $e]);
		}

		return json_encode($newDateTime !== '' ? $newDateTime : '');
	}

}
