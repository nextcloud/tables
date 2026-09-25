<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;

class FormattingRuleSetInput {
	private const VALID_TARGET_TYPES = ['row', 'column'];
	private const VALID_MODES = ['first-match', 'all-matches'];

	/** @param list<FormattingRuleInput> $rules */
	public function __construct(
		private readonly string $title,
		private readonly string $targetType,
		private readonly ?int $targetCol,
		private readonly string $mode,
		private readonly bool $enabled,
		private readonly array $rules,
	) {
	}

	public static function createFromInputArray(array $data): self {
		if (!isset($data['title'])) {
			throw new InvalidArgumentException('title is required');
		}

		$targetType = (string)($data['targetType'] ?? '');
		if (!in_array($targetType, self::VALID_TARGET_TYPES, true)) {
			throw new InvalidArgumentException('targetType must be "row" or "column"');
		}

		$targetCol = (isset($data['targetCol']) && $data['targetCol'] !== null)
			? (int)$data['targetCol']
			: null;
		if ($targetType === 'column' && $targetCol === null) {
			throw new InvalidArgumentException('targetCol is required when targetType is "column"');
		}

		$mode = (string)($data['mode'] ?? '');
		if (!in_array($mode, self::VALID_MODES, true)) {
			throw new InvalidArgumentException('mode must be "first-match" or "all-matches"');
		}

		$rules = [];
		if (isset($data['rules']) && is_array($data['rules'])) {
			foreach ($data['rules'] as $ruleData) {
				if (!is_array($ruleData)) {
					throw new InvalidArgumentException('Each rule must be an array');
				}
				$rules[] = FormattingRuleInput::createFromInputArray($ruleData);
			}
		}

		return new self(
			title: (string)$data['title'],
			targetType: $targetType,
			targetCol: $targetCol,
			mode: $mode,
			enabled: isset($data['enabled']) ? (bool)$data['enabled'] : true,
			rules: $rules,
		);
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function getTargetType(): string {
		return $this->targetType;
	}

	public function getTargetCol(): ?int {
		return $this->targetCol;
	}

	public function getMode(): string {
		return $this->mode;
	}

	public function isEnabled(): bool {
		return $this->enabled;
	}

	/** @return FormattingRuleInput[] */
	public function getRules(): array {
		return $this->rules;
	}

	public function toArray(): array {
		return [
			'title' => $this->title,
			'targetType' => $this->targetType,
			'targetCol' => $this->targetCol,
			'mode' => $this->mode,
			'enabled' => $this->enabled,
			'rules' => array_map(static fn (FormattingRuleInput $r) => $r->toArray(), $this->rules),
		];
	}
}
