<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\BackgroundJob;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
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
		private TableMapper $tableMapper,
		private ViewMapper $viewMapper,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array{user_id: string,
	 *     table_id: integer|null,
	 *     view_id: integer|null,
	 *     user_file_path: string|null,
	 *     import_file_name: string|null,
	 *     create_missing_columns: bool,
	 *     columns_config: array
	 * } $argument
	 */
	public function run($argument): void {
		$userId = $argument['user_id'];
		$tableId = $argument['table_id'];
		$viewId = $argument['view_id'];
		$oldUser = $this->userSession->getUser();

		try {
			$user = $this->userManager->get($userId);
			$this->userSession->setUser($user);

			$importStats = $this->importService
				->importV2(
					$userId,
					$tableId,
					$viewId,
					$argument['user_file_path'],
					$argument['import_file_name'],
					$argument['create_missing_columns'],
					$argument['columns_config']
				);
		} finally {
			$this->userSession->setUser($oldUser);
		}

		if (!$tableId && $viewId) {
			$tableId = $this->viewMapper->find($viewId)->getTableId();
		}

		$this->activityManager->triggerEvent(
			objectType: ActivityManager::TABLES_OBJECT_TABLE,
			object: $this->tableMapper->find($tableId),
			subject: ActivityManager::SUBJECT_IMPORT_FINISHED,
			additionalParams: [
				'importStats' => $importStats,
			],
			author: $userId
		);
	}
}
