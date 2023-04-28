<?php

namespace OCA\Tables\Service\ColumnTypes;

use DateTime;
use OCA\Tables\Db\Column;
use Psr\Log\LoggerInterface;

class SuperBusiness {

	protected LoggerInterface $logger;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	public function parseValue(string $value, ?Column $column = null): string {
		return json_encode($value);
	}

	protected function isValidDate(string $dateString, string $format): bool {
		$dateTime = DateTime::createFromFormat($format, $dateString);
		$newString = $dateTime ? $dateTime->format($format) : '';
		return $newString === $dateString;
	}
}
