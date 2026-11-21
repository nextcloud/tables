<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use OCA\Tables\Errors\BadRequestError;

/**
 * One entry of the order of a menu: an item and the group it sits in, or null.
 */
final class MenuOrderEntry {
	public function __construct(
		public readonly int $id,
		public readonly ?int $parentId,
	) {
	}

	/**
	 * Reads the order of a menu, a list of `{id, parentId}` in display order.
	 *
	 * @param array<array-key, mixed> $order
	 * @return list<self>
	 * @throws BadRequestError
	 */
	public static function listFromArray(array $order): array {
		if (!array_is_list($order)) {
			throw new BadRequestError('The order of a menu is a list of {id, parentId}, not an object.');
		}
		$entries = [];
		foreach ($order as $position => $entry) {
			$id = is_array($entry) ? ($entry['id'] ?? null) : null;
			$parentId = is_array($entry) ? ($entry['parentId'] ?? null) : null;
			if (!is_array($entry) || !is_int($id) || ($parentId !== null && !is_int($parentId)) || array_diff_key($entry, ['id' => true, 'parentId' => true]) !== []) {
				throw new BadRequestError('Entry ' . $position . ' needs the id of an item and the id of its group or null, and nothing else.');
			}
			$entries[] = new self($id, $parentId);
		}
		return $entries;
	}
}
