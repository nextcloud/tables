<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Activity;

use OCA\Tables\Model\ImportStats;
use OCP\Activity\IManager;

class ActivityManager {
	public function __construct(
		protected IManager $activityManager,
	) {
	}

	public function notifyImportFinished(string $userId, int $tableId, ImportStats $importStats): void {

		$activity = $this->activityManager->generateEvent();
		$activity->setApp(ActivityConstants::APP_ID)
			->setType(ActivityConstants::TYPE_IMPORT_FINISHED)
			->setAuthor($userId)
			->setObject('table', $tableId)
			->setAffectedUser($userId)
			->setSubject(ActivityConstants::SUBJECT_IMPORT_FINISHED, [
				'actor' => $userId,
				'tableId' => $tableId,
			])
			->setMessage(ActivityConstants::MESSAGE_IMPORT_FINISHED, [
				'actor' => $userId,
				'tableId' => $tableId,
			] + (array)$importStats);

		$this->activityManager->publish($activity);
	}
}
