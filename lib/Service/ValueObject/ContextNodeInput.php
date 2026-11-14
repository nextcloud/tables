<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

use InvalidArgumentException;
use JsonSerializable;
use OCA\Tables\AppInfo\Application;

/**
 * A table or view as it is requested for an application (context), together
 * with the permissions it is offered with, if they are given.
 */
class ContextNodeInput implements JsonSerializable {
	/** The values are stored in signed 32-bit integer columns */
	private const MAX_INTEGER_VALUE = 2_147_483_647;

	private const NODE_TYPES = [
		Application::NODE_TYPE_TABLE,
		Application::NODE_TYPE_VIEW,
	];

	private function __construct(
		private readonly int $nodeId,
		private readonly int $nodeType,
		private readonly ?int $permissions,
		private readonly ?int $order,
	) {
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function fromArray(array $inputNode): self {
		$nodeType = self::toInteger($inputNode['type'] ?? null, 'Unexpected node type');
		if (!in_array($nodeType, self::NODE_TYPES, true)) {
			throw new InvalidArgumentException('Unexpected node type');
		}

		return new self(
			nodeId: self::toInteger($inputNode['id'] ?? null, 'Unexpected node id'),
			nodeType: $nodeType,
			permissions: isset($inputNode['permissions'])
				? self::toPermissions($inputNode['permissions'])
				: null,
			order: isset($inputNode['order'])
				? self::toInteger($inputNode['order'], 'Unexpected node order')
				: null,
		);
	}

	/**
	 * @return array{id: int, type: int, permissions?: int, order?: int}
	 */
	public function jsonSerialize(): array {
		$node = [
			'id' => $this->nodeId,
			'type' => $this->nodeType,
		];
		if ($this->permissions !== null) {
			$node['permissions'] = $this->permissions;
		}
		if ($this->order !== null) {
			$node['order'] = $this->order;
		}
		return $node;
	}

	/**
	 * Accepts a bitmask of the grantable permissions that includes read. Read is
	 * the base permission of a node in an application: the application dialogs
	 * always grant it and offer create, update and delete on top of it.
	 *
	 * @throws InvalidArgumentException
	 */
	private static function toPermissions(mixed $value): int {
		$permissions = self::toInteger($value, 'Unexpected node permissions');
		if (($permissions & ~Application::PERMISSION_ALL) !== 0
			|| ($permissions & Application::PERMISSION_READ) === 0) {
			throw new InvalidArgumentException('Unexpected node permissions');
		}
		return $permissions;
	}

	/**
	 * Accepts integers and strings of digits, as form encoded requests carry
	 * every value as a string. Anything else is rejected instead of cast.
	 *
	 * @throws InvalidArgumentException
	 */
	private static function toInteger(mixed $value, string $errorMessage): int {
		if (is_string($value) && ctype_digit($value)) {
			// filter_var() reports a number that is too large instead of capping it, as a cast would
			$value = filter_var(ltrim($value, '0') ?: '0', FILTER_VALIDATE_INT);
		}

		if (!is_int($value) || $value < 0 || $value > self::MAX_INTEGER_VALUE) {
			throw new InvalidArgumentException($errorMessage);
		}
		return $value;
	}
}
