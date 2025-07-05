<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;

class EntitySuper extends Entity {
	protected const VIRTUAL_PROPERTIES = [];

	protected function markFieldUpdated(string $attribute): void {
		if (in_array($attribute, static::VIRTUAL_PROPERTIES, true)) {
			return;
		}
		parent::markFieldUpdated($attribute);
	}
}
