<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;

class FormattingRuleSetInput {
	use FormattingInputCast;

	public const MAX_TITLE_LENGTH = 255;

	public const MAX_RULES = 20;
	private const VALID_TARGET_TYPES = ['row', 'column'];
	private const VALID_MODES = ['first-match', 'all-matches'];

	public const VALID_MARKERS = ['circle', 'star', 'flag', 'alert', 'check'];

	/** @param list<FormattingRuleInput> $rules */
	public function __construct(
		private readonly string $title,
		private readonly string $targetType,
		private readonly ?int $targetCol,
		private readonly string $mode,
		private readonly bool $enabled,
		private readonly array $rules,
		private readonly ?string $marker = null,
	) {
	}

	public static function createFromInputArray(array $data): self {
		if (!isset($data['title'])) {
			throw new InvalidArgumentException('title is required');
		}

		$targetType = self::castString($data['targetType'] ?? '', 'targetType');
		if (!in_array($targetType, self::VALID_TARGET_TYPES, true)) {
			throw new InvalidArgumentException('targetType must be "row" or "column"');
		}

		$targetCol = (isset($data['targetCol']) && $data['targetCol'] !== null)
			? self::castInt($data['targetCol'], 'targetCol')
			: null;
		if ($targetType === 'column' && $targetCol === null) {
			throw new InvalidArgumentException('targetCol is required when targetType is "column"');
		}
		if ($targetType !== 'column') {
			$targetCol = null;
		}

		$mode = self::castString($data['mode'] ?? '', 'mode');
		if (!in_array($mode, self::VALID_MODES, true)) {
			throw new InvalidArgumentException('mode must be "first-match" or "all-matches"');
		}

		$marker = (isset($data['marker']) && $data['marker'] !== '') ? self::castString($data['marker'], 'marker') : null;
		if ($marker !== null && !in_array($marker, self::VALID_MARKERS, true)) {
			throw new InvalidArgumentException('marker must be one of: ' . implode(', ', self::VALID_MARKERS));
		}

		$rules = [];
		if (isset($data['rules']) && is_array($data['rules'])) {
			if (count($data['rules']) > self::MAX_RULES) {
				throw new InvalidArgumentException('Max ' . self::MAX_RULES . ' rules per rule set');
			}
			foreach ($data['rules'] as $ruleData) {
				if (!is_array($ruleData)) {
					throw new InvalidArgumentException('Each rule must be an array');
				}
				$rules[] = FormattingRuleInput::createFromInputArray($ruleData);
			}
		}

		return new self(
			title: self::castBoundedString($data['title'], 'title', self::MAX_TITLE_LENGTH),
			targetType: $targetType,
			targetCol: $targetCol,
			mode: $mode,
			enabled: isset($data['enabled']) ? self::castBool($data['enabled'], 'enabled') : true,
			rules: $rules,
			marker: $marker,
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

	public function getMarker(): ?string {
		return $this->marker;
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
