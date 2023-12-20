<?php

namespace OCA\Tables\Helper;

use OCP\DB\Types;

class ColumnsHelper {

	private array $columns;

	public function __construct() {
		$this->columns = [
			[
				'name' => 'text',
				'db_type' => Types::TEXT,
			],
			[
				'name' => 'number',
				'db_type' => Types::FLOAT,
			],
		];
	}

	/**
	 * @param string[] $keys Keys that should be returned
	 * @return array
	 */
	public function get(array $keys): array {
		$arr = [];
		foreach ($this->columns as $column) {
			$c = [];
			foreach ($keys as $key) {
				if (isset($column[$key])) {
					$c[$key] = $column[$key];
				} else {
					$c[$key] = null;
				}
			}
			$arr[] = $c;
		}

		if (count($keys) <= 1) {
			$out = [];
			foreach ($arr as $item) {
				$out[] = $item[$keys[0]];
			}
			return $out;
		}
		return $arr;
	}
}
