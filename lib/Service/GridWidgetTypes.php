<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\ResponseDefinitions;
use OCP\DB\Exception;
use OCP\IL10N;

/**
 * The widget types a grid view can hold. Every type has a schema for its configuration
 * and one for its content; the frontend renders its forms from them and stored grids
 * are validated against them.
 *
 * @psalm-import-type TablesGridWidgetValue from ResponseDefinitions
 * @psalm-import-type TablesGridWidget from ResponseDefinitions
 * @psalm-import-type TablesGridLayoutItem from ResponseDefinitions
 * @psalm-import-type TablesGridWidgetProperty from ResponseDefinitions
 * @psalm-import-type TablesGridWidgetType from ResponseDefinitions
 */
class GridWidgetTypes {
	public const TYPE_HEADER = 'header';
	public const TYPE_TEXT = 'text';
	public const TYPE_DATA = 'data';

	public const PROPERTY_TYPES = ['string', 'text', 'color', 'boolean', 'integer', 'enum', 'target'];

	private const HEX_COLOR = '/^#[0-9a-fA-F]{3,6}$/';
	private const LAYOUT_POSITIONS = ['gridX', 'gridY'];
	private const LAYOUT_SIZES = ['gridWidth', 'gridHeight'];

	public function __construct(
		private readonly IL10N $l10n,
		private readonly ViewMapper $viewMapper,
		private readonly TableMapper $tableMapper,
		private readonly PermissionsService $permissionsService,
	) {
	}

	/**
	 * @return array<string, TablesGridWidgetType>
	 */
	public function all(): array {
		return [
			self::TYPE_HEADER => [
				'type' => self::TYPE_HEADER,
				'title' => $this->l10n->t('Header'),
				'defaultWidth' => 12,
				'defaultHeight' => 2,
				'configuration' => $this->titleConfiguration(false),
				'properties' => [
					'title' => ['type' => 'string', 'title' => $this->l10n->t('Heading'), 'default' => '', 'required' => true],
					'subtitle' => ['type' => 'string', 'title' => $this->l10n->t('Subheading'), 'default' => ''],
					'textAlign' => ['type' => 'enum', 'title' => $this->l10n->t('Text alignment'), 'default' => 'left', 'options' => [
						['value' => 'left', 'label' => $this->l10n->t('Start')],
						['value' => 'center', 'label' => $this->l10n->t('Centered')],
						['value' => 'right', 'label' => $this->l10n->t('End')],
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
				'configuration' => $this->titleConfiguration(true),
				'properties' => [
					'text' => ['type' => 'text', 'title' => $this->l10n->t('Text'), 'default' => ''],
				],
			],
			self::TYPE_DATA => [
				'type' => self::TYPE_DATA,
				'title' => $this->l10n->t('Table or view'),
				'defaultWidth' => 12,
				'defaultHeight' => 5,
				'configuration' => $this->titleConfiguration(false),
				'properties' => [
					'target' => ['type' => 'target', 'title' => $this->l10n->t('Table or view'), 'default' => null, 'required' => true],
				],
			],
		];
	}

	/**
	 * @return array<string, TablesGridWidgetProperty>
	 */
	private function titleConfiguration(bool $showTitle): array {
		return [
			'title' => ['type' => 'string', 'title' => $this->l10n->t('Title'), 'default' => ''],
			'showTitle' => ['type' => 'boolean', 'title' => $this->l10n->t('Show the title'), 'default' => $showTitle],
		];
	}

	/**
	 * @return TablesGridWidgetType
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
	 * Validates a grid for the user who stores it and returns it with every widget reduced
	 * to what its schemas allow. The grid stored so far tells which targets are unchanged.
	 *
	 * @param array<array-key, mixed> $storedGrid
	 * @return array{widgets: list<TablesGridWidget>, layout: list<TablesGridLayoutItem>}
	 * @throws BadRequestError
	 * @throws Exception
	 */
	public function sanitizeGrid(mixed $grid, string $userId, array $storedGrid = []): array {
		if (!is_array($grid)) {
			throw new BadRequestError('The grid must be an object.');
		}
		foreach (['widgets', 'layout'] as $key) {
			if (isset($grid[$key]) && (!is_array($grid[$key]) || !array_is_list($grid[$key]))) {
				throw new BadRequestError('The grid property "' . $key . '" must be a list.');
			}
		}

		$widgets = [];
		foreach ($grid['widgets'] ?? [] as $widget) {
			if (!is_array($widget)) {
				throw new BadRequestError('Every widget needs a string id and type.');
			}
			$sanitized = $this->sanitizeWidget($widget);
			if (isset($widgets[$sanitized['id']])) {
				throw new BadRequestError('Two widgets share the id ' . $sanitized['id'] . '.');
			}
			$widgets[$sanitized['id']] = $sanitized;
		}
		$widgets = $this->checkTargets($widgets, $userId, $this->storedTargets($storedGrid));

		$layout = [];
		foreach ($grid['layout'] ?? [] as $item) {
			$sanitized = $this->sanitizeLayoutItem($item, $widgets);
			if (isset($layout[$sanitized['widgetId']])) {
				throw new BadRequestError('The layout places widget ' . $sanitized['widgetId'] . ' twice.');
			}
			$layout[$sanitized['widgetId']] = $sanitized;
		}

		return ['widgets' => array_values($widgets), 'layout' => array_values($layout)];
	}

	/**
	 * Returns a widget in the stored shape.
	 *
	 * @param array<array-key, mixed> $widget
	 * @return TablesGridWidget
	 * @throws BadRequestError
	 */
	public function sanitizeWidget(array $widget): array {
		if (!is_string($widget['id'] ?? null) || $widget['id'] === '' || !is_string($widget['type'] ?? null)) {
			throw new BadRequestError('Every widget needs a string id and type.');
		}
		$configuration = $widget['configuration'] ?? [];
		if (!is_array($configuration)) {
			throw new BadRequestError('The configuration of widget ' . $widget['id'] . ' must be an object.');
		}
		$content = $widget['content'] ?? [];
		if (!is_array($content)) {
			throw new BadRequestError('The content of widget ' . $widget['id'] . ' must be an object.');
		}
		$type = $this->get($widget['type']);
		return [
			'id' => $widget['id'],
			'type' => $widget['type'],
			'configuration' => $this->sanitizeProperties($widget['type'], $type['configuration'], $configuration),
			'content' => $this->sanitizeProperties($widget['type'], $type['properties'], $content),
		];
	}

	/**
	 * @param array<string, TablesGridWidget> $widgets
	 * @return TablesGridLayoutItem
	 * @throws BadRequestError
	 */
	private function sanitizeLayoutItem(mixed $item, array $widgets): array {
		if (!is_array($item) || !is_string($item['widgetId'] ?? null)) {
			throw new BadRequestError('Every layout item needs the id of its widget.');
		}
		if (!isset($widgets[$item['widgetId']])) {
			throw new BadRequestError('The layout places widget ' . $item['widgetId'] . ', which the grid does not have.');
		}
		foreach (self::LAYOUT_POSITIONS as $position) {
			if (!is_int($item[$position] ?? null) || $item[$position] < 0) {
				throw new BadRequestError('The layout position "' . $position . '" must be a non-negative integer.');
			}
		}
		foreach (self::LAYOUT_SIZES as $size) {
			if (!is_int($item[$size] ?? null) || $item[$size] < 1) {
				throw new BadRequestError('The layout size "' . $size . '" must be a positive integer.');
			}
		}
		return [
			'id' => $item['widgetId'],
			'widgetId' => $item['widgetId'],
			'gridX' => $item['gridX'],
			'gridY' => $item['gridY'],
			'gridWidth' => $item['gridWidth'],
			'gridHeight' => $item['gridHeight'],
		];
	}

	/**
	 * A data widget shows rows, so a new or changed target must exist, hold rows and be readable
	 * for the user; every unusable one gets the same error, so it reveals nothing about others'
	 * views. A target that was stored before is kept as it is, so a save does not depend on the
	 * saver's access to widgets they did not touch, and is marked broken once it no longer exists.
	 *
	 * @param array<string, TablesGridWidget> $widgets
	 * @param array<string, array{type: 'table'|'view', id: int}> $storedTargets
	 * @return array<string, TablesGridWidget>
	 * @throws BadRequestError
	 * @throws Exception
	 */
	private function checkTargets(array $widgets, string $userId, array $storedTargets): array {
		$targetIds = ['table' => [], 'view' => []];
		foreach ($widgets as $widget) {
			$target = $widget['content']['target'] ?? null;
			if (is_array($target)) {
				$targetIds[$target['type']][] = $target['id'];
			}
		}
		$elements = [
			'table' => $targetIds['table'] === [] ? [] : $this->tableMapper->findMany($targetIds['table']),
			'view' => $targetIds['view'] === [] ? [] : $this->viewMapper->findMany($targetIds['view']),
		];
		foreach ($widgets as $widgetId => $widget) {
			$target = $widget['content']['target'] ?? null;
			if (!is_array($target)) {
				continue;
			}
			$element = $elements[$target['type']][$target['id']] ?? null;
			$exists = $element !== null && !($element instanceof View && $element->isStandalone());
			if (($storedTargets[$widgetId] ?? null) === $target) {
				// an unchanged target is kept; one that no longer exists is marked until the widget is saved valid again
				if (!$exists) {
					$widgets[$widgetId]['broken'] = true;
				}
				continue;
			}
			if (!$exists || !$this->permissionsService->canReadRowsByElement($element, $target['type'], $userId)) {
				throw new BadRequestError('A table or view widget can only show a table or view whose rows you can read.');
			}
		}
		return $widgets;
	}

	/**
	 * The target of every widget in the stored grid, by widget id.
	 *
	 * @return array<string, array{type: 'table'|'view', id: int}>
	 */
	private function storedTargets(array $storedGrid): array {
		$targets = [];
		foreach (is_array($storedGrid['widgets'] ?? null) ? $storedGrid['widgets'] : [] as $widget) {
			$target = is_array($widget) ? ($widget['content']['target'] ?? null) : null;
			if (is_string($widget['id'] ?? null) && is_array($target) && in_array($target['type'] ?? null, ['table', 'view'], true) && is_int($target['id'] ?? null)) {
				$targets[$widget['id']] = ['type' => $target['type'], 'id' => $target['id']];
			}
		}
		return $targets;
	}

	/**
	 * Every property checked against its schema, defaults filled in and unknown properties dropped.
	 *
	 * @param array<string, TablesGridWidgetProperty> $schema
	 * @param array<array-key, mixed> $values
	 * @return array<string, TablesGridWidgetValue>
	 * @throws BadRequestError
	 */
	private function sanitizeProperties(string $type, array $schema, array $values): array {
		$sanitized = [];
		foreach ($schema as $name => $property) {
			$value = array_key_exists($name, $values) ? $values[$name] : $property['default'];
			$sanitized[$name] = $this->sanitizeProperty($type . '.' . $name, $property, $value);
		}
		return $sanitized;
	}

	/**
	 * @param TablesGridWidgetProperty $property
	 * @return TablesGridWidgetValue
	 * @throws BadRequestError
	 */
	private function sanitizeProperty(string $label, array $property, mixed $value): string|bool|int|array|null {
		$isRequired = $property['required'] ?? false;
		return match ($property['type']) {
			'string', 'text' => $this->sanitizeText($label, $value, $isRequired),
			'color' => $this->sanitizeColor($label, $value),
			'boolean' => is_bool($value) ? $value : throw new BadRequestError('Widget property ' . $label . ' must be true or false.'),
			'integer' => is_int($value) ? $value : throw new BadRequestError('Widget property ' . $label . ' must be an integer.'),
			'enum' => $this->sanitizeEnum($label, $value, array_column($property['options'] ?? [], 'value')),
			'target' => $this->sanitizeTarget($label, $value, $isRequired),
			default => throw new BadRequestError('Widget property ' . $label . ' has an unknown schema type.'),
		};
	}

	/**
	 * @throws BadRequestError
	 */
	private function sanitizeText(string $label, mixed $value, bool $isRequired): string {
		$value ??= '';
		if (!is_string($value)) {
			throw new BadRequestError('Widget property ' . $label . ' must be a string.');
		}
		if ($isRequired && trim($value) === '') {
			throw new BadRequestError('Widget property ' . $label . ' is required.');
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
			throw new BadRequestError('Widget property ' . $label . ' must be a hex color.');
		}
		return $value;
	}

	/**
	 * @param list<string> $allowed
	 * @throws BadRequestError
	 */
	private function sanitizeEnum(string $label, mixed $value, array $allowed): string {
		if (!is_string($value) || !in_array($value, $allowed, true)) {
			throw new BadRequestError('Widget property ' . $label . ' must be one of: ' . implode(', ', $allowed) . '.');
		}
		return $value;
	}

	/**
	 * @return array{type: 'table'|'view', id: int}|null
	 * @throws BadRequestError
	 */
	private function sanitizeTarget(string $label, mixed $value, bool $isRequired): ?array {
		if ($value === null) {
			if ($isRequired) {
				throw new BadRequestError('Widget property ' . $label . ' is required.');
			}
			return null;
		}
		$type = is_array($value) ? ($value['type'] ?? null) : null;
		$id = is_array($value) ? ($value['id'] ?? null) : null;
		if (($type !== 'table' && $type !== 'view') || !is_int($id) || $id <= 0) {
			throw new BadRequestError('Widget property ' . $label . ' must name a table or view by id.');
		}
		return ['type' => $type, 'id' => $id];
	}
}
