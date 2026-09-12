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

	/**
	 * Write a value that only describes the entity for the requesting user, such as
	 * the archived state resolved from their personal override.
	 *
	 * The column keeps its stored value for everyone else, so the write must not mark
	 * the field for persistence: the mappers cache an entity for the whole request and
	 * hand the same instance to every later consumer, one of which may well persist it.
	 * Whatever was pending before stays pending.
	 *
	 * @param string $property the entity property, as its setter names it
	 */
	public function setResolved(string $property, mixed $value): void {
		$pendingFields = $this->getUpdatedFields();
		$this->{'set' . ucfirst($property)}($value);

		$this->resetUpdatedFields();
		foreach (array_keys($pendingFields) as $field) {
			$this->markFieldUpdated($field);
		}
	}

	protected function markFieldUpdated(string $attribute): void {
		if (in_array($attribute, static::VIRTUAL_PROPERTIES, true)) {
			return;
		}
		parent::markFieldUpdated($attribute);
	}
}
