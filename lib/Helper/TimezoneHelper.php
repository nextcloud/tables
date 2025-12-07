<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use DateTimeZone;
use OCP\IConfig;
use OCP\IUserSession;

class TimezoneHelper {
	private ?string $timezone = null;

	public function __construct(
		private IConfig $config,
		private IUserSession $userSession,
	) {
	}

	public function applyUserTimezone(string $date): string {
		$userTimezone = $this->getUserTimezone();

		$dateTime = new \DateTimeImmutable($date, new DateTimeZone('UTC'));

		return $dateTime
			->setTimezone(new DateTimeZone($userTimezone))
			->format('Y-m-d H:i:s');
	}

	public function getUserTimezone(): string {
		if ($this->timezone) {
			return $this->timezone;
		}

		$userId = $this->userSession->getUser()?->getUID();
		$defaultTimeZone = $this->config->getSystemValueString('default_timezone', 'UTC');

		if ($userId) {
			$this->timezone = $this->config->getUserValue($userId, 'core', 'timezone', $defaultTimeZone);
		}

		return $this->timezone ?? $defaultTimeZone;
	}
}
