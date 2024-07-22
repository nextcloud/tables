<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class UsergroupBusiness extends SuperBusiness implements IColumnTypeBusiness {

	/**
	 * @param mixed $value (array|string|null)
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		if(!$column) {
			$this->logger->warning('No column given, but expected on '.__FUNCTION__.' within '.__CLASS__, ['exception' => new \Exception()]);
			return json_encode([]);
		}

		if($value === null) {
			return json_encode([]);
		}

		return json_encode($value);
	}

	/**
	 * @param string $value json encoded array{id: string, type: int}
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		if(!$column) {
			$this->logger->warning('No column given, but expected on '.__FUNCTION__.' within '.__CLASS__, ['exception' => new \Exception()]);
			return false;
		}

		if($value === null) {
			return true;
		}

		foreach (json_decode($value, true) as $v) {
			if((array_key_exists('id', $v) && !is_string($v['id'])) && (array_key_exists('type', $v) && !is_int($v['type']))) {
				return false;
			}
		}
		return true;
	}
}
