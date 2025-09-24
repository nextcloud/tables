<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Activity;

use OCP\AppFramework\Db\Entity;

class ChangeSet implements \JsonSerializable {

	public function __construct(
		private ?Entity $before = null,
		private ?Entity $after = null,
	) {
		if ($before !== null) {
			$this->setBefore($before);
		}
		if ($after !== null) {
			$this->setAfter($after);
		}
	}

	public function setBefore($before) {
		$this->before = clone $before;
	}

	public function setAfter($after) {
		$this->after = clone $after;
	}

	public function getBefore() {
		return $this->before;
	}

	public function getAfter() {
		return $this->after;
	}

	public function jsonSerialize(): array {
		return [
			'before' => $this->getBefore(),
			'after' => $this->getAfter()
		];
	}
}
