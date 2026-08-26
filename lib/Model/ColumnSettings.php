<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use Generator;
use JsonSerializable;
use OCA\Tables\Db\Column;
use OCA\Tables\Service\ValueObject\ColumnOrderInformation;
use OCA\Tables\Service\ValueObject\ViewColumnInformation;

class ColumnSettings implements JsonSerializable {
	/**
	 * @param ColumnOrderInformation[] $columnSettings
	 */
	public function __construct(
		protected array $columnSettings,
	) {
		foreach ($this->columnSettings as $columnSetting) {
			if (!$columnSetting instanceof ColumnOrderInformation) {
				throw new \InvalidArgumentException('Provided column settings must be of type ColumnOrderInformation');
			}
		}
	}

	public function columnInformation(): Generator {
		foreach ($this->columnSettings as $columnInformation) {
			yield $columnInformation;
		}
	}

	/**
	 * Creates column settings with only columnId and order (for table use).
	 */
	public static function createFromInputArray(array $inputColumnSettings, array $columnsMap = []): self {
		$columnSettings = [];
		foreach ($inputColumnSettings as $inputColumnSetting) {
			if (!is_array($inputColumnSetting)) {
				throw new \InvalidArgumentException('Each column settings entry must be an array');
			}

			// Resolve columnId from uuid if provided
			if (isset($inputColumnSetting['columnUuid']) && isset($columnsMap[$inputColumnSetting['columnUuid']]) && $columnsMap[$inputColumnSetting['columnUuid']] instanceof Column) {
				$inputColumnSetting['columnId'] = $columnsMap[$inputColumnSetting['columnUuid']]->getId();
			}
			if (!empty($columnsMap) && !isset($inputColumnSetting['columnId'])) {
				continue;
			}
			$columnSettings[] = ColumnOrderInformation::fromArray($inputColumnSetting);
		}
		return new self($columnSettings);
	}

	/**
	 * Creates column settings with view-specific fields (columnId, order, readonly, mandatory).
	 */
	public static function createViewSettingsFromInputArray(array $inputColumnSettings, array $columnsMap = []): self {
		$columnSettings = [];
		foreach ($inputColumnSettings as $inputColumnSetting) {
			if (!is_array($inputColumnSetting)) {
				throw new \InvalidArgumentException('Each column settings entry must be an array');
			}

			// Resolve columnId from uuid if provided
			if (isset($inputColumnSetting['columnUuid']) && isset($columnsMap[$inputColumnSetting['columnUuid']]) && $columnsMap[$inputColumnSetting['columnUuid']] instanceof Column) {
				$inputColumnSetting['columnId'] = $columnsMap[$inputColumnSetting['columnUuid']]->getId();
			}
			if (!empty($columnsMap) && !isset($inputColumnSetting['columnId'])) {
				continue;
			}
			$columnSettings[] = ViewColumnInformation::fromArray($inputColumnSetting);
		}
		return new self($columnSettings);
	}

	public function jsonSerialize(): array {
		return array_map(static fn (ColumnOrderInformation $vci) => $vci->jsonSerialize(), $this->columnSettings);
	}
}
