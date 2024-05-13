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
	 * @param mixed $value array{id: string, isUser: bool, displayName: string}
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

		foreach ($value as $v) {
			// TODO: maybe check if key exists first
			if(!is_string($v['id']) && !is_string($v['displayName']) && !is_bool($v['isUser'])) {
				return false;
			}
		}
		return true;
	}
}
