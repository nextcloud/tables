<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

use InvalidArgumentException;
use JsonSerializable;

class SortRule implements JsonSerializable {
	public function __construct(
		protected int $columnId,
		protected string $mode,
	) {
		if (!in_array($mode, ['ASC', 'DESC'], true)) {
			throw new InvalidArgumentException('Invalid sort mode provided, ASC or DESC are expected');
		}
	}

	public function jsonSerialize(): array {
		return [
			'columnId' => $this->columnId,
			'mode' => $this->mode,
		];
	}
}
