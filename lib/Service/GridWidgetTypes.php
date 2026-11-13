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
 * The widget types a page can hold. Every type has a schema for its settings;
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
	public const TYPE_TABS = 'tabs';

	public const GRID_COLUMNS = 12;
	public const HEADER_HEIGHT = 2;
	public const DATA_HEIGHT = 5;
	public const GRID_MAX_HEIGHT = 100;
	public const GRID_MAX_Y = 10000;
	public const MAX_WIDGETS_PER_PAGE = 100;
	public const MAX_TABS = 20;
	/** Bytes of the settings as stored JSON. */
	public const SETTINGS_MAX_BYTES = 60000;

	private const HEX_COLOR = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/D';

	public function __construct(
		private readonly IL10N $l10n,
	) {
	}

	/**
	 * @return array<string, TablesWidgetType>
	 */
	public function all(): array {
		return [
			self::TYPE_HEADER => [
				'type' => self::TYPE_HEADER,
				'title' => $this->l10n->t('Header'),
				'defaultWidth' => self::GRID_COLUMNS,
				'defaultHeight' => self::HEADER_HEIGHT,
				'hasSource' => false,
				'settings' => [
					'heading' => ['type' => 'string', 'title' => $this->l10n->t('Heading'), 'default' => '', 'required' => true],
					'subheading' => ['type' => 'string', 'title' => $this->l10n->t('Subheading'), 'default' => ''],
					'textAlign' => ['type' => 'enum', 'title' => $this->l10n->t('Text alignment'), 'default' => 'start', 'options' => [
						['value' => 'start', 'label' => $this->l10n->t('Start')],
						['value' => 'center', 'label' => $this->l10n->t('Centered')],
						['value' => 'end', 'label' => $this->l10n->t('End')],
					]],
					'backgroundColor' => ['type' => 'color', 'title' => $this->l10n->t('Background color'), 'default' => ''],
					'textColor' => ['type' => 'color', 'title' => $this->l10n->t('Text color'), 'default' => ''],
				],
			],
			self::TYPE_TEXT => [
				'type' => self::TYPE_TEXT,
				'title' => $this->l10n->t('Description'),
				'defaultWidth' => 6,
				'defaultHeight' => 2,
				'hasSource' => false,
				'settings' => [
					'text' => ['type' => 'text', 'title' => $this->l10n->t('Text'), 'default' => ''],
				],
			],
			self::TYPE_DATA => [
				'type' => self::TYPE_DATA,
				'title' => $this->l10n->t('Table or view'),
				'defaultWidth' => self::GRID_COLUMNS,
				'defaultHeight' => self::DATA_HEIGHT,
				'hasSource' => true,
				'settings' => [],
			],
			self::TYPE_TABS => [
				'type' => self::TYPE_TABS,
				'title' => $this->l10n->t('Tabs'),
				'defaultWidth' => self::GRID_COLUMNS,
				'defaultHeight' => 8,
				'hasSource' => false,
				'settings' => [
					'tabs' => ['type' => 'text', 'title' => $this->l10n->t('Tabs, one per line'), 'default' => '', 'required' => true],
				],
			],
		];
	}

	/**
	 * The widgets a start page starts with: a header with the name and description of its
	 * application, then one full width table or view widget per node, in the given order,
	 * as far as a page holds them. Without a name there is no header. The header passes the
	 * schema of its type; a description longer than a page description is cut.
	 *
	 * @param list<int> $nodeRelIds node relations of the application, in page order
	 * @return list<WidgetInput>
	 */
	public function startPageWidgets(string $name, string $description, array $nodeRelIds): array {
		$widgets = [];
		$gridY = 0;
		$heading = TextHelper::trim($name);
		if ($heading !== '') {
			$settings = $this->sanitizeSettings(self::TYPE_HEADER, [
				'heading' => mb_substr($heading, 0, TextHelper::TITLE_MAX_LENGTH),
				'subheading' => mb_substr(TextHelper::trim($description), 0, TextHelper::DESCRIPTION_MAX_LENGTH),
			]);
			$widgets[] = new WidgetInput(null, self::TYPE_HEADER, null, null, null, 0, $gridY, self::GRID_COLUMNS, self::HEADER_HEIGHT, $settings, null);
			$gridY += self::HEADER_HEIGHT;
		}
		foreach (array_slice($nodeRelIds, 0, self::MAX_WIDGETS_PER_PAGE - count($widgets)) as $nodeRelId) {
			$widgets[] = new WidgetInput(null, self::TYPE_DATA, null, null, null, 0, $gridY, self::GRID_COLUMNS, self::DATA_HEIGHT, [], $nodeRelId);
			$gridY += self::DATA_HEIGHT;
		}
		return $widgets;
	}

	/**
	 * The titles of the tabs of a tabs widget: the non-empty lines of its `tabs` setting.
	 *
	 * @param array<string, TablesWidgetValue> $settings
	 * @return list<string>
	 */
	public static function tabTitles(array $settings): array {
		$lines = array_map(trim(...), explode("\n", (string)($settings['tabs'] ?? '')));
		return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
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
			'color' => $this->sanitizeColor($label, $value),
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
	 * @throws BadRequestError
	 */
	private function sanitizeColor(string $label, mixed $value): string {
		if ($value === null || $value === '') {
			return '';
		}
		if (!is_string($value) || !preg_match(self::HEX_COLOR, $value)) {
			throw new BadRequestError('Widget setting ' . $label . ' must be a hex color.');
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
	 * The title of a widget, trimmed, or null when it has none.
	 *
	 * @throws BadRequestError
	 */
	public function validateTitle(int $index, mixed $title): ?string {
		try {
			return TextHelper::optional($title, TextHelper::TITLE_MAX_LENGTH);
		} catch (\InvalidArgumentException) {
			throw new BadRequestError('The title of widget ' . $index . ' must be a text of at most ' . TextHelper::TITLE_MAX_LENGTH . ' characters.');
		}
	}

	/**
	 * The description of a widget, trimmed, or null when it has none.
	 *
	 * @throws BadRequestError
	 */
	public function validateDescription(int $index, mixed $description): ?string {
		try {
			return TextHelper::optional($description, TextHelper::DESCRIPTION_MAX_LENGTH);
		} catch (\InvalidArgumentException) {
			throw new BadRequestError('The description of widget ' . $index . ' must be a text of at most ' . TextHelper::DESCRIPTION_MAX_LENGTH . ' characters.');
		}
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
		$icon = $widget['icon'] ?? null;
		if ($icon !== null && (!is_string($icon) || ($icon !== '' && !IconHelper::isValid($icon)))) {
			throw new BadRequestError('The icon of widget ' . $index . ' must be the name of an icon.');
		}
		$source = $widget['source'] ?? null;
		if ($source !== null && !$typeSchema['hasSource']) {
			throw new BadRequestError('Widget ' . $index . ' is a ' . $type . ' widget, which shows no table or view.');
		}
		$nodeRelId = is_array($source) ? ($source['nodeRelId'] ?? null) : null;
		if ($source !== null && !is_int($nodeRelId)) {
			throw new BadRequestError('The source of widget ' . $index . ' must be an object with a numeric nodeRelId.');
		}
		$parentIndex = $widget['parentIndex'] ?? null;
		if ($parentIndex !== null && !is_int($parentIndex)) {
			throw new BadRequestError('The parentIndex of widget ' . $index . ' must be a number.');
		}
		$parentTab = $widget['parentTab'] ?? null;
		if ($parentTab !== null && !is_int($parentTab)) {
			throw new BadRequestError('The parentTab of widget ' . $index . ' must be a number.');
		}
		$position = $this->validatePosition($index, $widget);
		$settings = $this->sanitizeSettings($type, $widget['settings'] ?? null);
		if ($type === self::TYPE_TABS && count(self::tabTitles($settings)) > self::MAX_TABS) {
			throw new BadRequestError('Widget ' . $index . ' has more than ' . self::MAX_TABS . ' tabs.');
		}

		return new WidgetInput(
			$id,
			$type,
			$this->validateTitle($index, $widget['title'] ?? null),
			$icon === '' ? null : $icon,
			$this->validateDescription($index, $widget['description'] ?? null),
			$position['gridX'],
			$position['gridY'],
			$position['gridWidth'],
			$position['gridHeight'],
			$settings,
			$nodeRelId,
			$parentIndex,
			$parentTab,
		);
	}

	/**
	 * Checks where one widget of a list sits: on the page, or in a tab of a tabs widget of
	 * the same list that sits on the page itself, so tabs go one level deep.
	 *
	 * @param list<WidgetInput> $widgets
	 * @throws BadRequestError
	 */
	public function validateParent(int $index, array $widgets): void {
		$widget = $widgets[$index];
		if ($widget->parentIndex === null) {
			if ($widget->parentTab !== null) {
				throw new BadRequestError('Widget ' . $index . ' names a tab but no tabs widget.');
			}
			return;
		}
		$parent = $widgets[$widget->parentIndex] ?? null;
		if ($widget->parentIndex === $index || $parent === null || $parent->type !== self::TYPE_TABS) {
			throw new BadRequestError('Widget ' . $index . ' must sit in a tabs widget of the same page.');
		}
		if ($parent->parentIndex !== null || $widget->type === self::TYPE_TABS) {
			throw new BadRequestError('Widget ' . $index . ' would nest tabs more than one level deep.');
		}
		$tabCount = count(self::tabTitles($parent->settings));
		if ($widget->parentTab === null || $widget->parentTab < 0 || $widget->parentTab >= $tabCount) {
			throw new BadRequestError('Widget ' . $index . ' must name one of the ' . $tabCount . ' tabs of its tabs widget.');
		}
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
