<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Errors\BadRequestError;

/**
 * The widget types a grid view can hold, each with two schemas: one for its
 * configuration (how the widget behaves on the page: title, whether the title
 * shows) and one for its content (what the widget shows).
 *
 * A schema is a small JSON schema subset: every property has a type (string,
 * text, color, boolean, integer, enum or target), a title, a default and, for
 * enum, its options. The frontend renders both forms from it and the server
 * validates what is stored against it, so a widget never stores what its form
 * would refuse.
 *
 * A stored widget is {id, type, configuration, content}; its position lives in
 * the grid's layout list. Grids saved before the configuration existed carried
 * title and showTitle on the widget itself; sanitizeWidget upgrades them.
 *
 * @psalm-type GridWidgetProperty = array{type: string, title: string, default: mixed, options?: list<array{value: string, label: string}>, required?: bool}
 * @psalm-type GridWidgetType = array{type: string, title: string, defaultWidth: int, defaultHeight: int, configuration: array<string, GridWidgetProperty>, properties: array<string, GridWidgetProperty>}
 * @psalm-type GridWidget = array{id: string, type: string, configuration: array<string, mixed>, content: array<string, mixed>}
 */
class GridWidgetTypes {
	public const TYPE_HEADER = 'header';
	public const TYPE_TEXT = 'text';
	public const TYPE_DATA = 'data';

	public const PROPERTY_TYPES = ['string', 'text', 'color', 'boolean', 'integer', 'enum', 'target'];

	private const HEX_COLOR = '/^#[0-9a-fA-F]{3,6}$/';

	/**
	 * @return array<string, GridWidgetType>
	 */
	public static function all(): array {
		return [
			self::TYPE_HEADER => [
				'type' => self::TYPE_HEADER,
				'title' => 'Header',
				'defaultWidth' => 12,
				'defaultHeight' => 2,
				'configuration' => [
					'title' => ['type' => 'string', 'title' => 'Title', 'default' => ''],
					'showTitle' => ['type' => 'boolean', 'title' => 'Show the title', 'default' => false],
				],
				'properties' => [
					'title' => ['type' => 'string', 'title' => 'Heading', 'default' => '', 'required' => true],
					'subtitle' => ['type' => 'string', 'title' => 'Subheading', 'default' => ''],
					'textAlign' => ['type' => 'enum', 'title' => 'Text alignment', 'default' => 'left', 'options' => [
						['value' => 'left', 'label' => 'Left'],
						['value' => 'center', 'label' => 'Centered'],
						['value' => 'right', 'label' => 'Right'],
					]],
					'backgroundColor' => ['type' => 'color', 'title' => 'Background color', 'default' => ''],
					'textColor' => ['type' => 'color', 'title' => 'Text color', 'default' => ''],
				],
			],
			self::TYPE_TEXT => [
				'type' => self::TYPE_TEXT,
				'title' => 'Description',
				'defaultWidth' => 6,
				'defaultHeight' => 2,
				'configuration' => [
					'title' => ['type' => 'string', 'title' => 'Title', 'default' => ''],
					'showTitle' => ['type' => 'boolean', 'title' => 'Show the title', 'default' => true],
				],
				'properties' => [
					'text' => ['type' => 'text', 'title' => 'Text', 'default' => ''],
				],
			],
			self::TYPE_DATA => [
				'type' => self::TYPE_DATA,
				'title' => 'Table or view',
				'defaultWidth' => 12,
				'defaultHeight' => 5,
				'configuration' => [
					'title' => ['type' => 'string', 'title' => 'Title', 'default' => ''],
					'showTitle' => ['type' => 'boolean', 'title' => 'Show the title', 'default' => false],
				],
				'properties' => [
					'target' => ['type' => 'target', 'title' => 'Table or view', 'default' => null, 'required' => true],
				],
			],
		];
	}

	/**
	 * @return GridWidgetType
	 * @throws BadRequestError
	 */
	public static function get(string $type): array {
		$types = self::all();
		if (!isset($types[$type])) {
			throw new BadRequestError('Unknown widget type "' . $type . '", expected one of: ' . implode(', ', array_keys($types)));
		}
		return $types[$type];
	}

	/**
	 * Returns a widget in the stored shape {id, type, configuration, content}, both
	 * schemas applied. A widget saved before the configuration existed carried
	 * title and showTitle on itself; they move into the configuration here.
	 *
	 * @param array<string, mixed> $widget
	 * @return GridWidget
	 * @throws BadRequestError
	 */
	public static function sanitizeWidget(array $widget): array {
		if (!is_string($widget['id'] ?? null) || !is_string($widget['type'] ?? null)) {
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
		return [
			'id' => $widget['id'],
			'type' => $widget['type'],
			'configuration' => self::sanitizeConfiguration($widget['type'], $configuration),
			'content' => self::sanitizeContent($widget['type'], $content),
		];
	}

	/**
	 * Returns the configuration with every property checked against the schema,
	 * defaults filled in and unknown properties dropped.
	 *
	 * @param array<string, mixed> $configuration
	 * @return array<string, mixed>
	 * @throws BadRequestError
	 */
	public static function sanitizeConfiguration(string $type, array $configuration): array {
		$sanitized = [];
		foreach (self::get($type)['configuration'] as $name => $property) {
			$value = array_key_exists($name, $configuration) ? $configuration[$name] : $property['default'];
			$sanitized[$name] = self::sanitizeProperty($type, $name, $property, $value);
		}
		return $sanitized;
	}

	/**
	 * Returns the content with every property checked against the schema, defaults
	 * filled in and unknown properties dropped.
	 *
	 * @param array<string, mixed> $content
	 * @return array<string, mixed>
	 * @throws BadRequestError
	 */
	public static function sanitizeContent(string $type, array $content): array {
		if ($type === self::TYPE_DATA && !isset($content['target']) && isset($content['targetType'], $content['targetId'])) {
			// grids saved before the target became one property
			$content['target'] = ['type' => $content['targetType'], 'id' => (int)$content['targetId']];
		}
		$sanitized = [];
		foreach (self::get($type)['properties'] as $name => $property) {
			$value = array_key_exists($name, $content) ? $content[$name] : $property['default'];
			$sanitized[$name] = self::sanitizeProperty($type, $name, $property, $value);
		}
		return $sanitized;
	}

	/**
	 * @param GridWidgetProperty $property
	 * @throws BadRequestError
	 */
	private static function sanitizeProperty(string $type, string $name, array $property, mixed $value): mixed {
		$label = $type . '.' . $name;
		switch ($property['type']) {
			case 'string':
			case 'text':
				if ($value === null) {
					$value = '';
				}
				if (!is_string($value)) {
					throw new BadRequestError('Widget property ' . $label . ' must be a string.');
				}
				if (($property['required'] ?? false) && trim($value) === '') {
					throw new BadRequestError('Widget property ' . $label . ' is required.');
				}
				return $value;
			case 'color':
				if ($value === null || $value === '') {
					return '';
				}
				if (!is_string($value) || !preg_match(self::HEX_COLOR, $value)) {
					throw new BadRequestError('Widget property ' . $label . ' must be a hex color.');
				}
				return $value;
			case 'boolean':
				return (bool)$value;
			case 'integer':
				if (!is_int($value) && !(is_string($value) && is_numeric($value))) {
					throw new BadRequestError('Widget property ' . $label . ' must be an integer.');
				}
				return (int)$value;
			case 'enum':
				$allowed = array_column($property['options'] ?? [], 'value');
				if (!in_array($value, $allowed, true)) {
					throw new BadRequestError('Widget property ' . $label . ' must be one of: ' . implode(', ', $allowed) . '.');
				}
				return $value;
			case 'target':
				if ($value === null) {
					if ($property['required'] ?? false) {
						throw new BadRequestError('Widget property ' . $label . ' is required.');
					}
					return null;
				}
				if (!is_array($value) || !in_array($value['type'] ?? null, ['table', 'view'], true) || !is_int($value['id'] ?? null) || $value['id'] <= 0) {
					throw new BadRequestError('Widget property ' . $label . ' must name a table or view by id.');
				}
				return ['type' => $value['type'], 'id' => $value['id']];
		}
		throw new BadRequestError('Widget property ' . $label . ' has an unknown schema type.');
	}
}
