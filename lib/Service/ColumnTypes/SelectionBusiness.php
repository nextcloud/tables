<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class SelectionBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function parseValue(string $value, ?Column $column = null): string {
		if(!$column) {
			$this->logger->warning('No column given, but expected on parseValue for SelectionBusiness');
			return '';
		}

		foreach ($column->getSelectionOptionsArray() as $option) {
			if($option['label'] === $value) {
				return json_encode($option['id']);
			}
		}
		return '';
	}

}
