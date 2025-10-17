<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use OCA\Tables\Constants\UsergroupType;
use OCA\Tables\Db\Column;

class ColumnsHelper {

	public array $columns = [
		Column::TYPE_TEXT,
		Column::TYPE_NUMBER,
		Column::TYPE_DATETIME,
		Column::TYPE_SELECTION,
		Column::TYPE_USERGROUP,
	];

	public function __construct(
		private UserHelper $userHelper,
		private CircleHelper $circleHelper,
	) {
	}

	public function resolveSearchValue(string $placeholder, string $userId, ?Column $column = null): string|array {
		if (str_starts_with($placeholder, '@selection-id-')) {
			return substr($placeholder, 14);
		}

		$placeholderParts = explode(':', $placeholder, 2);
		$placeholderName = ltrim($placeholderParts[0], '@');
		$additionalValue = $placeholderParts[1] ?? null;

		switch ($placeholderName) {
			case 'me':
				if ($column?->getType() !== Column::TYPE_USERGROUP) {
					return $userId;
				}

				$value = [UsergroupType::USER => $userId];
				if ($column?->getUsergroupSelectGroups()) {
					$value[UsergroupType::GROUP] = $this->userHelper->getGroupIdsForUser($userId);
				}

				if ($column?->getUsergroupSelectTeams()) {
					$value[UsergroupType::CIRCLE] = $this->circleHelper->getCircleIdsForUser($userId);
				}

				return $value;
			case 'my-name': return $this->userHelper->getUserDisplayName($userId);
			case 'checked': return 'true';
			case 'unchecked': return 'false';
			case 'stars-0': return '0';
			case 'stars-1': return '1';
			case 'stars-2': return '2';
			case 'stars-3': return '3';
			case 'stars-4': return '4';
			case 'stars-5': return '5';
			case 'datetime-date-today': return date('Y-m-d') ? date('Y-m-d') : '';
			case 'datetime-date-start-of-year': return date('Y-01-01') ? date('Y-01-01') : '';
			case 'datetime-date-start-of-month': return date('Y-m-01') ? date('Y-m-01') : '';
			case 'datetime-date-start-of-week':
				$day = date('w');
				$result = date('Y-m-d', strtotime('-' . $day . ' days'));
				return  $result ?: '';
			case 'datetime-time-now': return date('H:i');
			case 'datetime-now': return date('Y-m-d H:i') ? date('Y-m-d H:i') : '';
			case 'datetime-exact-date': return $additionalValue ?? '';
			case 'datetime-days-ahead':
				$days = max(0, (int)$additionalValue);
				return date('Y-m-d', strtotime("+{$days} days")) ?: '';
			case 'datetime-days-ago':
				$days = max(0, (int)$additionalValue);
				return date('Y-m-d', strtotime("-{$days} days")) ?: '';
			default: return $placeholder;
		}
	}
}
