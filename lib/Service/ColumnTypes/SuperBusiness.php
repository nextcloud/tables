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
		try {
			$dateTime = new DateTime($dateString);
			$newString = $dateTime->format($format);
			return $newString === $dateString;
		} catch (\Exception $e) {
			$this->logger->warning('Could not validate date: '.$e->getMessage().' Returning false.');
			return false;
		}
	}
}
