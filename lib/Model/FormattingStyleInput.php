<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;

class FormattingStyleInput {
	use FormattingInputCast;

	private const VALID_KEYS = ['backgroundColor', 'textColor', 'fontWeight', 'fontStyle', 'textDecoration'];

	private const HEX_COLOR_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\z/';

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

		$backgroundColor = isset($data['backgroundColor']) ? self::castString($data['backgroundColor'], 'backgroundColor') : null;
		if ($backgroundColor !== null && !preg_match(self::HEX_COLOR_PATTERN, $backgroundColor)) {
			throw new InvalidArgumentException('backgroundColor must be a 3- or 6-digit hex color');
		}

		$textColor = isset($data['textColor']) ? self::castString($data['textColor'], 'textColor') : null;
		if ($textColor !== null && !preg_match(self::HEX_COLOR_PATTERN, $textColor)) {
			throw new InvalidArgumentException('textColor must be a 3- or 6-digit hex color');
		}

		$fontWeight = isset($data['fontWeight']) ? self::castString($data['fontWeight'], 'fontWeight') : null;
		if ($fontWeight !== null && $fontWeight !== 'bold') {
			throw new InvalidArgumentException('fontWeight must be "bold"');
		}

		$fontStyle = isset($data['fontStyle']) ? self::castString($data['fontStyle'], 'fontStyle') : null;
		if ($fontStyle !== null && $fontStyle !== 'italic') {
			throw new InvalidArgumentException('fontStyle must be "italic"');
		}

		$textDecoration = isset($data['textDecoration']) ? self::castString($data['textDecoration'], 'textDecoration') : null;
		if ($textDecoration !== null && !in_array($textDecoration, ['strikethrough', 'underline'], true)) {
			throw new InvalidArgumentException('textDecoration must be "strikethrough" or "underline"');
		}

		return new self(
			backgroundColor: $backgroundColor,
			textColor: $textColor,
			fontWeight: $fontWeight,
			fontStyle: $fontStyle,
			textDecoration: $textDecoration,
		);
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
