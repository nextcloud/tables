<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class SelectionBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function parseValue(?string $value = null, ?Column $column = null): string {
		if(!$column) {
			$this->logger->warning('No column given, but expected on parseValue for SelectionBusiness');
			return '';
		}

		$intValue = (int) $value;
		if ("".$intValue === $value) {
			// if it seems to be an option ID
			foreach ($column->getSelectionOptionsArray() as $option) {
				if($option['id'] === $intValue && $option['label'] !== $value) {
					return json_encode($option['id']);
				}
			}
		} else {
			foreach ($column->getSelectionOptionsArray() as $option) {
				if($option['label'] === $value) {
					return json_encode($option['id']);
				}
			}
		}

		return '';
	}

	public function canBeParsed(?string $value = null, ?Column $column = null): bool {
		if(!$column) {
			return false;
		}
		if($value === null) {
			return true;
		}

		$intValue = (int) $value;
		if ("".$intValue === $value) {
			// if it seems to be an option ID
			foreach ($column->getSelectionOptionsArray() as $option) {
				if($option['id'] === $intValue && $option['label'] !== $value) {
					return true;
				}
			}
		} else {
			foreach ($column->getSelectionOptionsArray() as $option) {
				if($option['label'] === $value) {
					return true;
				}
			}
		}

		return false;
	}

}
