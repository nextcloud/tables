<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class SelectionMultiBusiness extends SuperBusiness implements IColumnTypeBusiness {

	private array $options = [];

	public function parseValue(string $value, ?Column $column = null): string {
		if(!$column) {
			$this->logger->warning('No column given, but expected on parseValue for SelectionBusiness');
			return '';
		}

		$this->options = $column->getSelectionOptionsArray();
		$wantedValues = explode(',', $value);
		$result = [];

		foreach ($wantedValues as $wantedValue) {
			$wantedValue = trim($wantedValue);
			if(($id = $this->getOptionIdForValue($wantedValue)) !== null) {
				$result[] = $id;
			}
		}
		return json_encode($result);
	}

	private function getOptionIdForValue(string $value): ?int {
		foreach ($this->options as $option) {
			if($option['label'] === $value) {
				return $option['id'];
			}
		}
		return null;
	}

}
