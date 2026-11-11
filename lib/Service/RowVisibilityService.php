<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\View;

/**
 * Resolves which users see a row in a view, memoized so that activities and
 * notifications of one row operation share one evaluation. RowService drops
 * the entry with forget() when the operation ends.
 */
class RowVisibilityService {
	/** @var array<int, array<string, array<string, bool>>> rowId => view key => userId => visible */
	private array $memo = [];

	public function __construct(
		private readonly Row2Mapper $row2Mapper,
	) {
	}

	/**
	 * @param array<array-key> $userIds
	 * @return list<string>
	 */
	public function usersSeeingRow(int $rowId, View $view, array $userIds): array {
		$userIds = array_map(strval(...), $userIds);
		$viewKey = $view->getId() . ':' . md5((string)$view->getFilter());
		$known = $this->memo[$rowId][$viewKey] ?? [];

		$unknown = array_values(array_filter($userIds, static fn (string $userId): bool => !isset($known[$userId])));
		if ($unknown !== []) {
			$visible = array_fill_keys($this->row2Mapper->filterUsersSeeingRow($rowId, $view, $unknown), true);
			foreach ($unknown as $userId) {
				$known[$userId] = isset($visible[$userId]);
			}
			$this->memo[$rowId][$viewKey] = $known;
		}

		return array_values(array_filter($userIds, static fn (string $userId): bool => $known[$userId]));
	}

	public function forget(int $rowId): void {
		unset($this->memo[$rowId]);
	}
}
