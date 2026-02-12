<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use Generator;
use JsonSerializable;
use OCA\Tables\Service\ValueObject\ViewColumnInformation;

class ColumnSettings implements JSONSerializable {
	/**
	 * @param ViewColumnInformation[] $columnSettings
	 */
	public function __construct(
		protected array $columnSettings,
	) {
		foreach ($this->columnSettings as $columnSetting) {
			if (!$columnSetting instanceof ViewColumnInformation) {
				throw new \InvalidArgumentException('Provided column settings must be of type ViewColumnInformation');
			}
		}
	}

	public function columnInformation(): Generator {
		foreach ($this->columnSettings as $columnInformation) {
			yield $columnInformation;
		}
	}

	public static function createFromInputArray(array $inputColumnSettings): self {
		$columnSettings = [];
		foreach ($inputColumnSettings as $inputColumnSetting) {
			$columnSettings[] = ViewColumnInformation::fromArray($inputColumnSetting);
		}
		return new self($columnSettings);
	}

	public function jsonSerialize(): mixed {
		return $this->columnSettings;
	}
}
