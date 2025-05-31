<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Activity;

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

class Setting extends ActivitySettings {
	public function __construct(
		protected IL10N $l,
	) {
	}

	public function getIdentifier(): string {
		return ActivityConstants::TYPE_IMPORT_FINISHED;
	}

	public function getName(): string {
		return $this->l->t('<strong>Import</strong> of a file has finished');
	}

	public function getGroupIdentifier() {
		return ActivityConstants::APP_ID;
	}

	public function getGroupName() {
		return $this->l->t('Tables');
	}

	public function getPriority(): int {
		return 50;
	}

	public function canChangeStream(): bool {
		return true;
	}

	public function isDefaultEnabledStream(): bool {
		return true;
	}

	public function canChangeMail(): bool {
		return true;
	}

	public function isDefaultEnabledMail(): bool {
		return false;
	}
}
