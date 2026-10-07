<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Errors\BadRequestError;

/**
 * The widget types a grid view can hold, each with the schema of its content.
 *
 * The schema is a small JSON schema subset: every property has a type (string,
 * text, color, boolean, integer, enum or target), a title, a default and, for
 * enum, its options. The frontend renders the configuration form from it and
 * the server validates stored content against it, so a widget never stores
 * what its form would refuse.
 *
 * @psalm-type GridWidgetProperty = array{type: string, title: string, default: mixed, options?: list<array{value: string, label: string}>, required?: bool}
 * @psalm-type GridWidgetType = array{type: string, title: string, showTitle: bool, defaultWidth: int, defaultHeight: int, properties: array<string, GridWidgetProperty>}
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
				'showTitle' => false,
				'defaultWidth' => 12,
				'defaultHeight' => 2,
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
				'showTitle' => true,
				'defaultWidth' => 6,
				'defaultHeight' => 2,
				'properties' => [
					'text' => ['type' => 'text', 'title' => 'Text', 'default' => ''],
				],
			],
			self::TYPE_DATA => [
				'type' => self::TYPE_DATA,
				'title' => 'Table or view',
				'showTitle' => false,
				'defaultWidth' => 12,
				'defaultHeight' => 5,
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
