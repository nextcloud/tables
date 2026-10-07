<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Constants\ColumnType;
use OCA\Tables\Db\Column;

/**
 * Describes a table's columns as a JSON Schema (draft 2020-12) document.
 *
 * This is a standard interchange format, separate from the app's own table
 * scheme export. Each column becomes a property keyed by its technical name;
 * mandatory columns are required. Column types without a mapping yet (users
 * and groups, relations) accept any value.
 */
class JsonSchemaExportService {
	public const DIALECT = 'https://json-schema.org/draft/2020-12/schema';

	/**
	 * @param Column[] $columns
	 * @return array<string, mixed>
	 */
	public function export(string $title, string $description, array $columns): array {
		$properties = [];
		$required = [];
		foreach ($columns as $column) {
			$name = $this->propertyName($column);
			$properties[$name] = $this->describeColumn($column);
			if ($column->getMandatory() === true) {
				$required[] = $name;
			}
		}

		$schema = [
			'$schema' => self::DIALECT,
			'title' => $title,
			'type' => 'object',
			'properties' => (object)$properties,
		];
		if ($description !== '') {
			$schema['description'] = $description;
		}
		if ($required !== []) {
			$schema['required'] = $required;
		}

		return $schema;
	}

	private function propertyName(Column $column): string {
		$technicalName = $column->getTechnicalName();
		if ($technicalName !== null && $technicalName !== '') {
			return $technicalName;
		}

		return $column->getTitle();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describeColumn(Column $column): array {
		$property = match (ColumnType::from($column->getType())) {
			ColumnType::TEXT => $this->describeText($column),
			ColumnType::NUMBER => $this->describeNumber($column),
			ColumnType::SELECTION => $this->describeSelection($column),
			ColumnType::DATETIME => $this->describeDatetime($column),
			ColumnType::PEOPLE, ColumnType::RELATION => [],
		};

		$property['title'] = $column->getTitle();
		$description = $column->getDescription();
		if ($description !== null && $description !== '') {
			$property['description'] = $description;
		}

		return $property;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describeText(Column $column): array {
		$property = ['type' => 'string'];

		$maxLength = $column->getTextMaxLength();
		if ($maxLength !== null && $maxLength > 0) {
			$property['maxLength'] = $maxLength;
		}

		$pattern = $column->getTextAllowedPattern();
		if ($pattern !== null && $pattern !== '') {
			$property['pattern'] = $pattern;
		}

		return $property;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describeNumber(Column $column): array {
		$property = ['type' => $column->getNumberDecimals() === 0 ? 'integer' : 'number'];

		$minimum = $column->getNumberMin();
		if ($minimum !== null) {
			$property['minimum'] = $minimum;
		}

		$maximum = $column->getNumberMax();
		if ($maximum !== null) {
			$property['maximum'] = $maximum;
		}

		return $property;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describeSelection(Column $column): array {
		if ($column->getSubtype() === Column::SUBTYPE_SELECTION_CHECK) {
			return ['type' => 'boolean'];
		}

		$labels = array_map(
			static fn (array $option): string => (string)$option['label'],
			$column->getSelectionOptionsArray() ?? [],
		);

		if ($column->getSubtype() === Column::SUBTYPE_SELECTION_MULTI) {
			return [
				'type' => 'array',
				'items' => ['enum' => $labels],
				'uniqueItems' => true,
			];
		}

		return ['enum' => $labels];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function describeDatetime(Column $column): array {
		$format = match ($column->getSubtype()) {
			Column::SUBTYPE_DATETIME_DATE => 'date',
			Column::SUBTYPE_DATETIME_TIME => 'time',
			default => 'date-time',
		};

		return ['type' => 'string', 'format' => $format];
	}
}
