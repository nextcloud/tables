<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;

class FormattingStyleInput {
	private const VALID_KEYS = ['backgroundColor', 'textColor', 'fontWeight', 'fontStyle', 'textDecoration'];

	public function __construct(
		private readonly ?string $backgroundColor,
		private readonly ?string $textColor,
		private readonly ?string $fontWeight,
		private readonly ?string $fontStyle,
		private readonly ?string $textDecoration,
	) {
	}

	public static function createFromInputArray(array $data): self {
		$unknown = array_diff(array_keys($data), self::VALID_KEYS);
		if (!empty($unknown)) {
			throw new InvalidArgumentException('Unknown style key(s): ' . implode(', ', $unknown));
		}

		$bg = isset($data['backgroundColor']) ? (string)$data['backgroundColor'] : null;
		if ($bg !== null && !preg_match('/^#[0-9a-fA-F]{3,6}$/', $bg)) {
			throw new InvalidArgumentException('backgroundColor must be a 3- or 6-digit hex color');
		}

		$tc = isset($data['textColor']) ? (string)$data['textColor'] : null;
		if ($tc !== null && !preg_match('/^#[0-9a-fA-F]{3,6}$/', $tc)) {
			throw new InvalidArgumentException('textColor must be a 3- or 6-digit hex color');
		}

		$fw = isset($data['fontWeight']) ? (string)$data['fontWeight'] : null;
		if ($fw !== null && $fw !== 'bold') {
			throw new InvalidArgumentException('fontWeight must be "bold"');
		}

		$fs = isset($data['fontStyle']) ? (string)$data['fontStyle'] : null;
		if ($fs !== null && $fs !== 'italic') {
			throw new InvalidArgumentException('fontStyle must be "italic"');
		}

		$td = isset($data['textDecoration']) ? (string)$data['textDecoration'] : null;
		if ($td !== null && !in_array($td, ['strikethrough', 'underline'], true)) {
			throw new InvalidArgumentException('textDecoration must be "strikethrough" or "underline"');
		}

		return new self($bg, $tc, $fw, $fs, $td);
	}

	public function toArray(): array {
		$result = [];
		if ($this->backgroundColor !== null) {
			$result['backgroundColor'] = $this->backgroundColor;
		}
		if ($this->textColor !== null) {
			$result['textColor'] = $this->textColor;
		}
		if ($this->fontWeight !== null) {
			$result['fontWeight'] = $this->fontWeight;
		}
		if ($this->fontStyle !== null) {
			$result['fontStyle'] = $this->fontStyle;
		}
		if ($this->textDecoration !== null) {
			$result['textDecoration'] = $this->textDecoration;
		}
		return $result;
	}
}
