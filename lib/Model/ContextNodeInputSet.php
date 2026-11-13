<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use JsonSerializable;
use OCA\Tables\Service\ValueObject\ContextNodeInput;

/**
 * The complete list of tables and views requested for an application (context).
 */
class ContextNodeInputSet implements JsonSerializable {
	/**
	 * @param list<ContextNodeInput> $nodes
	 */
	private function __construct(
		private readonly array $nodes,
	) {
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function createFromInputArray(array $inputNodes): self {
		if (!array_is_list($inputNodes)) {
			throw new InvalidArgumentException('Nodes must be given as a list');
		}

		$nodes = [];
		foreach ($inputNodes as $inputNode) {
			if (!is_array($inputNode)) {
				throw new InvalidArgumentException('Each node must be an object');
			}
			$nodes[] = ContextNodeInput::fromArray($inputNode);
		}
		return new self($nodes);
	}

	/**
	 * @return list<array{id: int, type: int, permissions?: int, order?: int}>
	 */
	public function jsonSerialize(): array {
		return array_map(static fn (ContextNodeInput $node): array => $node->jsonSerialize(), $this->nodes);
	}
}
