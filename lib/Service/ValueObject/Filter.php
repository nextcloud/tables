<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

use JsonSerializable;
use OCA\Tables\Constants\FilterOperator;

class Filter implements JsonSerializable {
	public function __construct(
		protected readonly int $columnId,
		protected readonly FilterOperator $operator,
		protected readonly string $value,
	) {
	}

	public function jsonSerialize(): array {
		return [
			'columnId' => $this->columnId,
			'operator' => $this->operator->value,
			'value' => $this->value,
		];
	}
}
