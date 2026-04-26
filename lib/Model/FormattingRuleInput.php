<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;

class FormattingRuleInput {
	public function __construct(
		private readonly string $title,
		private readonly FormattingConditionSetInput $condition,
		private readonly FormattingStyleInput $format,
		private readonly bool $enabled,
	) {
	}

	public static function createFromInputArray(array $data): self {
		if (!isset($data['title'])) {
			throw new InvalidArgumentException('title is required');
		}
		if (!isset($data['condition']) || !is_array($data['condition'])) {
			throw new InvalidArgumentException('condition must be an array');
		}
		if (!isset($data['format']) || !is_array($data['format'])) {
			throw new InvalidArgumentException('format must be an array');
		}

		return new self(
			title: (string)$data['title'],
			condition: FormattingConditionSetInput::createFromInputArray($data['condition']),
			format: FormattingStyleInput::createFromInputArray($data['format']),
			enabled: isset($data['enabled']) ? (bool)$data['enabled'] : true,
		);
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function getCondition(): FormattingConditionSetInput {
		return $this->condition;
	}

	public function getFormat(): FormattingStyleInput {
		return $this->format;
	}

	public function isEnabled(): bool {
		return $this->enabled;
	}

	public function toArray(): array {
		return [
			'title' => $this->title,
			'enabled' => $this->enabled,
			'condition' => $this->condition->toArray(),
			'format' => $this->format->toArray(),
		];
	}
}
