<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;

class FormattingRuleInput {
	private const MAX_ID_LENGTH = 36;

	public function __construct(
		private readonly string $title,
		private readonly FormattingConditionSetInput $condition,
		private readonly FormattingStyleInput $format,
		private readonly bool $enabled,
		private readonly ?string $id = null,
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

		$id = null;
		if (isset($data['id']) && $data['id'] !== '') {
			$id = (string)$data['id'];
			if (strlen($id) > self::MAX_ID_LENGTH) {
				throw new InvalidArgumentException('id must be at most ' . self::MAX_ID_LENGTH . ' characters');
			}
		}

		return new self(
			title: (string)$data['title'],
			condition: FormattingConditionSetInput::createFromInputArray($data['condition']),
			format: FormattingStyleInput::createFromInputArray($data['format']),
			enabled: isset($data['enabled']) ? (bool)$data['enabled'] : true,
			id: $id,
		);
	}

	/**
	 * Identifier of the rule this input refers to, if the client sent one. Only honoured
	 * when it matches a rule that is already stored, so a client cannot choose new ids.
	 */
	public function getId(): ?string {
		return $this->id;
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
			'id' => $this->id,
			'title' => $this->title,
			'enabled' => $this->enabled,
			'condition' => $this->condition->toArray(),
			'format' => $this->format->toArray(),
		];
	}
}
