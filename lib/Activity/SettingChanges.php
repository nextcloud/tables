<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Activity;

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

class SettingChanges extends ActivitySettings {

	public function __construct(
		protected IL10N $l,
	) {
	}

	public function getGroupIdentifier() {
		return 'tables';
	}

	public function getGroupName() {
		return $this->l->t('Tables');
	}

	/**
	 * @return string Lowercase a-z and underscore only identifier
	 * @since 20.0.0
	 */
	public function getIdentifier(): string {
		return 'tables';
	}

	/**
	 * @return string A translated string
	 * @since 20.0.0
	 */
	public function getName(): string {
		return $this->l->t('A <strong>table or row</strong> was changed');
	}

	/**
	 * @return int whether the filter should be rather on the top or bottom of
	 *             the admin section. The filters are arranged in ascending order of the
	 *             priority values. It is required to return a value between 0 and 100.
	 * @since 20.0.0
	 */
	public function getPriority(): int {
		return 90;
	}

	/**
	 * Left in for backwards compatibility
	 *
	 * @return bool
	 * @since 20.0.0
	 */
	public function canChangeStream(): bool {
		return true;
	}

	/**
	 * Left in for backwards compatibility
	 *
	 * @return bool
	 * @since 20.0.0
	 */
	public function isDefaultEnabledStream(): bool {
		return true;
	}

	/**
	 * @return bool True when the option can be changed for the mail
	 * @since 20.0.0
	 */
	public function canChangeMail(): bool {
		return true;
	}

	/**
	 * @return bool Whether or not an activity email should be send by default
	 * @since 20.0.0
	 */
	public function isDefaultEnabledMail(): bool {
		return false;
	}
}
