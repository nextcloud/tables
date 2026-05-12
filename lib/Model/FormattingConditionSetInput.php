<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;

class FormattingConditionSetInput {
	private const MAX_GROUPS = 10;

	/** @param list<FormattingConditionGroupInput> $groups */
	private function __construct(
		private readonly array $groups,
	) {
	}

	public static function createFromInputArray(array $data): self {
		if (!isset($data['groups']) || !is_array($data['groups'])) {
			throw new InvalidArgumentException('groups must be an array');
		}
		if (empty($data['groups'])) {
			throw new InvalidArgumentException('At least one condition group is required');
		}
		if (count($data['groups']) > self::MAX_GROUPS) {
			throw new InvalidArgumentException('Max ' . self::MAX_GROUPS . ' groups per condition set');
		}

		$groups = [];
		foreach ($data['groups'] as $groupData) {
			if (!is_array($groupData)) {
				throw new InvalidArgumentException('Each group must be an array');
			}
			$groups[] = FormattingConditionGroupInput::createFromInputArray($groupData);
		}

		return new self($groups);
	}

	public function toArray(): array {
		return [
			'groups' => array_map(
				static fn (FormattingConditionGroupInput $g) => $g->toArray(),
				$this->groups
			),
		];
	}

	/** @return int[] */
	public function collectColumnIds(): array {
		$ids = [];
		foreach ($this->groups as $group) {
			$ids = array_merge($ids, $group->collectColumnIds());
		}
		return array_values(array_unique($ids));
	}
}
