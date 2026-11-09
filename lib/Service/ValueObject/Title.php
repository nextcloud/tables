<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

use OCA\Tables\Helper\TextHelper;
use Stringable;

class Title implements Stringable {
	public function __construct(
		protected string $title,
	) {
		$this->title = $this->normalize($this->title);

		if ($this->title === '') {
			throw new \InvalidArgumentException('Title is missing.');
		}

		if (strlen($this->title) > 200) {
			throw new \InvalidArgumentException('Title exceed maximum length of 200 bytes');
		}
	}

	public function __toString(): string {
		return $this->title;
	}

	private function normalize(string $title): string {
		return TextHelper::trim($title);
	}
}
