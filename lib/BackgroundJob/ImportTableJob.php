<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\BackgroundJob;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Service\ImportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\IUserManager;
use OCP\IUserSession;

class ImportTableJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private IUserManager $userManager,
		private IUserSession $userSession,
		private ImportService $importService,
		private ActivityManager $activityManager,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array $argument
	 */
	public function run($argument): void {
		$userId = $argument['user_id'];
		$tableId = $argument['table_id'];
		$viewId = $argument['view_id'];
		$path = $argument['path'];
		$createMissingColumns = $argument['create_missing_columns'] ?? false;
		$columnsConfig = $argument['columns_config'] ?? null;

		$oldUser = $this->userSession->getUser();
		try {
			$user = $this->userManager->get($userId);
			$this->userSession->setUser($user);

			//fixme: handle errors
			$importStats = $this->importService
				->import($userId, $tableId, $viewId, $path, $createMissingColumns, $columnsConfig);
		} catch (\Throwable $e) {
			throw $e;
		} finally {
			$this->userSession->setUser($oldUser);
		}

		$this->activityManager->notifyImportFinished($userId, $tableId, $importStats);
	}
}
