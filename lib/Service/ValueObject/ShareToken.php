<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

use InvalidArgumentException;
use Stringable;

class ShareToken implements Stringable {
	public const MIN_LENGTH = 16;
	public const MAX_LENGTH = 255;
	public const CHARACTER_REGEX = '/[^A-Za-z0-9]/';

	/**
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		private readonly string $token,
	) {
		$lengthInBytes = strlen($this->token);
		if ($lengthInBytes < self::MIN_LENGTH
			|| $lengthInBytes > self::MAX_LENGTH
		) {
			throw new InvalidArgumentException('Share token has to be between 16 and 255 bytes long');
		}

		if (preg_match(self::CHARACTER_REGEX, $this->token) === 1) {
			throw new InvalidArgumentException('Share token contains invalid characters: ' . self::CHARACTER_REGEX);
		}
	}

	public function __toString() {
		return $this->token;
	}
}
