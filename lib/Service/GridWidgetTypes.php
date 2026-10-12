<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCP\IL10N;

/**
 * The widget types a grid view can hold. Every type has a schema for its configuration
 * and one for its content; the frontend renders its forms from them and stored grids
 * are validated against them.
 *
 * @psalm-type GridWidgetProperty = array{type: string, title: string, default: mixed, options?: list<array{value: string, label: string}>, required?: bool}
 * @psalm-type GridWidgetType = array{type: string, title: string, defaultWidth: int, defaultHeight: int, configuration: array<string, GridWidgetProperty>, properties: array<string, GridWidgetProperty>}
 * @psalm-type GridWidget = array{id: string, type: string, configuration: array<string, mixed>, content: array<string, mixed>}
 * @psalm-type GridLayoutItem = array{id: string, widgetId: string, gridX: int, gridY: int, gridWidth: int, gridHeight: int}
 */
class GridWidgetTypes {
	public const TYPE_HEADER = 'header';
	public const TYPE_TEXT = 'text';
	public const TYPE_DATA = 'data';

	public const PROPERTY_TYPES = ['string', 'text', 'color', 'boolean', 'integer', 'enum', 'target'];

	private const HEX_COLOR = '/^#[0-9a-fA-F]{3,6}$/';
	private const LAYOUT_POSITIONS = ['gridX', 'gridY', 'gridWidth', 'gridHeight'];

	public function __construct(
		private readonly IL10N $l10n,
		private readonly ViewMapper $viewMapper,
	) {
	}

	/**
	 * @return array<string, GridWidgetType>
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
	 * @return array<string, GridWidgetProperty>
	 */
	private function titleConfiguration(bool $showTitle): array {
		return [
			'title' => ['type' => 'string', 'title' => $this->l10n->t('Title'), 'default' => ''],
			'showTitle' => ['type' => 'boolean', 'title' => $this->l10n->t('Show the title'), 'default' => $showTitle],
		];
	}

	/**
	 * @return GridWidgetType
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
	 * Validates a grid and returns it with every widget reduced to what its schemas allow.
	 *
	 * @return array{widgets: list<GridWidget>, layout: list<GridLayoutItem>}
	 * @throws BadRequestError
	 */
	public function sanitizeGrid(mixed $grid): array {
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
		$this->assertTargetsAreNotGridViews($widgets);

		$layout = [];
		foreach ($grid['layout'] ?? [] as $item) {
			$layout[] = $this->sanitizeLayoutItem($item, $widgets);
		}

		return ['widgets' => array_values($widgets), 'layout' => $layout];
	}

	/**
	 * Returns a widget in the stored shape. A widget saved before the configuration existed
	 * carried title and showTitle on itself; they move into the configuration.
	 *
	 * @param array<array-key, mixed> $widget
	 * @return GridWidget
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
		foreach (['title', 'showTitle'] as $legacy) {
			if (array_key_exists($legacy, $widget) && !array_key_exists($legacy, $configuration)) {
				$configuration[$legacy] = $widget[$legacy];
			}
		}
		$content = $widget['content'] ?? [];
		if (!is_array($content)) {
			throw new BadRequestError('The content of widget ' . $widget['id'] . ' must be an object.');
		}
		$type = $this->get($widget['type']);
		if ($widget['type'] === self::TYPE_DATA && !isset($content['target']) && isset($content['targetType'], $content['targetId'])) {
			$content['target'] = ['type' => $content['targetType'], 'id' => (int)$content['targetId']];
		}
		return [
			'id' => $widget['id'],
			'type' => $widget['type'],
			'configuration' => $this->sanitizeProperties($widget['type'], $type['configuration'], $configuration),
			'content' => $this->sanitizeProperties($widget['type'], $type['properties'], $content),
		];
	}

	/**
	 * @param array<string, GridWidget> $widgets
	 * @return GridLayoutItem
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
	 * A data widget shows rows, so it cannot point at another grid view.
	 *
	 * @param array<string, GridWidget> $widgets
	 * @throws BadRequestError
	 */
	private function assertTargetsAreNotGridViews(array $widgets): void {
		$viewIds = [];
		foreach ($widgets as $widget) {
			$target = $widget['content']['target'] ?? null;
			if (is_array($target) && $target['type'] === 'view') {
				$viewIds[] = $target['id'];
			}
		}
		if ($viewIds === []) {
			return;
		}
		foreach ($this->viewMapper->findMany($viewIds) as $view) {
			if ($view->getTableId() === null) {
				throw new BadRequestError('A table or view widget cannot show the view ' . $view->getId() . ', which has no table.');
			}
		}
	}

	/**
	 * Every property checked against its schema, defaults filled in and unknown properties dropped.
	 *
	 * @param array<string, GridWidgetProperty> $schema
	 * @param array<array-key, mixed> $values
	 * @return array<string, mixed>
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
	 * @param GridWidgetProperty $property
	 * @throws BadRequestError
	 */
	private function sanitizeProperty(string $label, array $property, mixed $value): mixed {
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
	 * @param list<mixed> $allowed
	 * @throws BadRequestError
	 */
	private function sanitizeEnum(string $label, mixed $value, array $allowed): mixed {
		if (!in_array($value, $allowed, true)) {
			throw new BadRequestError('Widget property ' . $label . ' must be one of: ' . implode(', ', $allowed) . '.');
		}
		return $value;
	}

	/**
	 * @return array{type: string, id: int}|null
	 * @throws BadRequestError
	 */
	private function sanitizeTarget(string $label, mixed $value, bool $isRequired): ?array {
		if ($value === null) {
			if ($isRequired) {
				throw new BadRequestError('Widget property ' . $label . ' is required.');
			}
			return null;
		}
		if (!is_array($value) || !in_array($value['type'] ?? null, ['table', 'view'], true) || !is_int($value['id'] ?? null) || $value['id'] <= 0) {
			throw new BadRequestError('Widget property ' . $label . ' must name a table or view by id.');
		}
		return ['type' => $value['type'], 'id' => $value['id']];
	}
}
