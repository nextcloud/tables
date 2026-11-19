<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\Widget;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Helper\IconHelper;
use OCA\Tables\Helper\TextHelper;
use OCA\Tables\Model\WidgetInput;
use OCA\Tables\ResponseDefinitions;
use OCP\IL10N;

/**
 * The widget types a grid page can hold. Every type has a schema for its settings;
 * the frontend renders its form from it and saved widgets are validated against it.
 * A type with a source shows a table or view of the application.
 *
 * @psalm-import-type TablesWidgetValue from ResponseDefinitions
 * @psalm-import-type TablesWidgetSettingSchema from ResponseDefinitions
 * @psalm-import-type TablesWidgetType from ResponseDefinitions
 */
class GridWidgetTypes {
	public const TYPE_HEADER = 'header';
	public const TYPE_TEXT = 'text';
	public const TYPE_DATA = 'data';
	public const TYPE_TABLES = 'tables';

	public const GRID_COLUMNS = 12;
	public const GRID_MAX_HEIGHT = 100;
	public const GRID_MAX_Y = 10000;
	public const MAX_WIDGETS_PER_PAGE = 100;
	/** Bytes of the settings as stored JSON. */
	public const SETTINGS_MAX_BYTES = 60000;

	/** @var array<string, TablesWidgetType>|null */
	private ?array $types = null;

	public function __construct(
		private readonly IL10N $l10n,
	) {
	}

	/**
	 * @return array<string, TablesWidgetType>
	 */
	public function all(): array {
		return $this->types ??= [
			self::TYPE_HEADER => [
				'type' => self::TYPE_HEADER,
				'title' => $this->l10n->t('Header'),
				'defaultWidth' => 12,
				'defaultHeight' => 2,
				'hasSource' => false,
				'settings' => [
					'heading' => ['type' => 'string', 'title' => $this->l10n->t('Heading'), 'default' => '', 'required' => true],
					'subheading' => ['type' => 'string', 'title' => $this->l10n->t('Subheading'), 'default' => ''],
					'textAlign' => ['type' => 'enum', 'title' => $this->l10n->t('Text alignment'), 'default' => 'start', 'options' => [
						['value' => 'start', 'label' => $this->l10n->t('Start')],
						['value' => 'center', 'label' => $this->l10n->t('Centered')],
						['value' => 'end', 'label' => $this->l10n->t('End')],
					]],
					// colour pairs of the theme, so the header stays readable in light, dark and high contrast
					'color' => ['type' => 'enum', 'title' => $this->l10n->t('Color'), 'default' => 'primary', 'options' => [
						['value' => 'primary', 'label' => $this->l10n->t('Primary')],
						['value' => 'neutral', 'label' => $this->l10n->t('Neutral')],
						['value' => 'success', 'label' => $this->l10n->t('Success')],
						['value' => 'warning', 'label' => $this->l10n->t('Warning')],
						['value' => 'error', 'label' => $this->l10n->t('Error')],
					]],
				],
			],
			self::TYPE_TEXT => [
				'type' => self::TYPE_TEXT,
				'title' => $this->l10n->t('Text'),
				'defaultWidth' => 6,
				'defaultHeight' => 2,
				'hasSource' => false,
				'settings' => [
					'text' => ['type' => 'text', 'title' => $this->l10n->t('Text'), 'default' => '', 'hint' => $this->l10n->t('Leave an empty line between paragraphs')],
				],
			],
			self::TYPE_DATA => [
				'type' => self::TYPE_DATA,
				'title' => $this->l10n->t('Table or view'),
				'defaultWidth' => 12,
				'defaultHeight' => 5,
				'hasSource' => true,
				'settings' => [],
			],
			self::TYPE_TABLES => [
				'type' => self::TYPE_TABLES,
				'title' => $this->l10n->t('Tables and views'),
				'defaultWidth' => 12,
				'defaultHeight' => 8,
				'hasSource' => false,
				'settings' => [],
			],
		];
	}

	/**
	 * @return TablesWidgetType
	 * @throws BadRequestError
	 */
	public function get(string $type): array {
		$types = $this->all();
		if (!isset($types[$type])) {
			throw new BadRequestError('Unknown widget type "' . $type . '", expected one of: ' . implode(', ', array_keys($types)));
		}
		return $types[$type];
	}

	/**
	 * Checks the settings of a widget against the schema of its type and returns them
	 * with defaults filled in and unknown settings dropped. Stored as JSON they take at
	 * most {@see self::SETTINGS_MAX_BYTES} bytes.
	 *
	 * @return array<string, TablesWidgetValue>
	 * @throws BadRequestError
	 */
	public function sanitizeSettings(string $type, mixed $settings): array {
		$schema = $this->get($type)['settings'];
		$settings ??= [];
		if (!is_array($settings)) {
			throw new BadRequestError('The settings of a ' . $type . ' widget must be an object.');
		}
		$sanitized = [];
		foreach ($schema as $name => $property) {
			$value = array_key_exists($name, $settings) ? $settings[$name] : $property['default'];
			$sanitized[$name] = $this->sanitizeValue($type . '.' . $name, $property, $value);
		}
		$json = json_encode((object)$sanitized, Widget::SETTINGS_JSON_FLAGS);
		if ($json === false) {
			throw new BadRequestError('The settings of a ' . $type . ' widget must be valid UTF-8 text.');
		}
		if (strlen($json) > self::SETTINGS_MAX_BYTES) {
			throw new BadRequestError('The settings of a ' . $type . ' widget take more than ' . self::SETTINGS_MAX_BYTES . ' bytes.');
		}
		return $sanitized;
	}

	/**
	 * @param TablesWidgetSettingSchema $property
	 * @return TablesWidgetValue
	 * @throws BadRequestError
	 */
	private function sanitizeValue(string $label, array $property, mixed $value): string {
		return match ($property['type']) {
			'string', 'text' => $this->sanitizeText($label, $value, $property['required'] ?? false),
			'enum' => $this->sanitizeEnum($label, $value, array_column($property['options'] ?? [], 'value')),
		};
	}

	/**
	 * @throws BadRequestError
	 */
	private function sanitizeText(string $label, mixed $value, bool $isRequired): string {
		$value ??= '';
		if (!is_string($value)) {
			throw new BadRequestError('Widget setting ' . $label . ' must be a string.');
		}
		if ($isRequired && TextHelper::trim($value) === '') {
			throw new BadRequestError('Widget setting ' . $label . ' is required.');
		}
		return $value;
	}

	/**
	 * @param list<string> $allowed
	 * @throws BadRequestError
	 */
	private function sanitizeEnum(string $label, mixed $value, array $allowed): string {
		if (!is_string($value) || !in_array($value, $allowed, true)) {
			throw new BadRequestError('Widget setting ' . $label . ' must be one of: ' . implode(', ', $allowed) . '.');
		}
		return $value;
	}

	/**
	 * Reads one widget as the API and the import receive it, with every rule its type
	 * sets. `source` is `{nodeRelId}` as given; whether that node belongs to the
	 * application is for the caller to check.
	 *
	 * @throws BadRequestError
	 */
	public function readWidget(int $index, mixed $widget): WidgetInput {
		if (!is_array($widget)) {
			throw new BadRequestError('Widget ' . $index . ' must be an object.');
		}
		$type = $widget['type'] ?? null;
		if (!is_string($type)) {
			throw new BadRequestError('Widget ' . $index . ' needs a type.');
		}
		$typeSchema = $this->get($type);
		$id = $widget['id'] ?? null;
		if ($id !== null && !is_int($id)) {
			throw new BadRequestError('The id of widget ' . $index . ' must be a number.');
		}
		$icon = IconHelper::optional($widget['icon'] ?? null, 'widget ' . $index);
		$source = $widget['source'] ?? null;
		if ($source !== null && !$typeSchema['hasSource']) {
			throw new BadRequestError('Widget ' . $index . ' is a ' . $type . ' widget, which shows no table or view.');
		}
		$nodeRelId = is_array($source) ? ($source['nodeRelId'] ?? null) : null;
		if ($source !== null && !is_int($nodeRelId)) {
			throw new BadRequestError('The source of widget ' . $index . ' must be an object with a numeric nodeRelId.');
		}
		$position = $this->validatePosition($index, $widget);

		return new WidgetInput(
			$id,
			$type,
			TextHelper::title($widget['title'] ?? null, 'widget ' . $index),
			$icon,
			TextHelper::description($widget['description'] ?? null, 'widget ' . $index),
			$position['gridX'],
			$position['gridY'],
			$position['gridWidth'],
			$position['gridHeight'],
			$this->sanitizeSettings($type, $widget['settings'] ?? null),
			$nodeRelId,
		);
	}

	/**
	 * The place of a widget on a grid of {@see self::GRID_COLUMNS} columns.
	 *
	 * @param array<array-key, mixed> $widget
	 * @return array{gridX: int, gridY: int, gridWidth: int, gridHeight: int}
	 * @throws BadRequestError
	 */
	public function validatePosition(int $index, array $widget): array {
		$limits = [
			'gridX' => [0, self::GRID_COLUMNS - 1],
			'gridY' => [0, self::GRID_MAX_Y],
			'gridWidth' => [1, self::GRID_COLUMNS],
			'gridHeight' => [1, self::GRID_MAX_HEIGHT],
		];
		$position = [];
		foreach ($limits as $name => [$minimum, $maximum]) {
			$value = $widget[$name] ?? null;
			if (!is_int($value) || $value < $minimum || $value > $maximum) {
				throw new BadRequestError('Widget ' . $index . ' needs ' . $name . ' as a whole number from ' . $minimum . ' to ' . $maximum . '.');
			}
			$position[$name] = $value;
		}
		if ($position['gridX'] + $position['gridWidth'] > self::GRID_COLUMNS) {
			throw new BadRequestError('Widget ' . $index . ' does not fit in ' . self::GRID_COLUMNS . ' columns.');
		}
		return $position;
	}
}
