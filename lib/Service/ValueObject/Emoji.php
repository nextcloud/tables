<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

use InvalidArgumentException;
use OCP\IEmojiHelper;
use OCP\Server;
use Stringable;

class Emoji implements Stringable {
	public function __construct(
		protected string $emoji,
	) {
		$this->emoji = trim($this->emoji);
		$validator = Server::get(IEmojiHelper::class);
		if (!$validator->isValidSingleEmoji($this->emoji)) {
			throw new InvalidArgumentException('Only a single, valid emoji may be passed');
		}
	}

	public function __toString(): string {
		return $this->emoji;
	}
}
