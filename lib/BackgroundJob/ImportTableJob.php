<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\BackgroundJob;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\ImportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ImportTableJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly IUserManager $userManager,
		private readonly IUserSession $userSession,
		private readonly ImportService $importService,
		private readonly ActivityManager $activityManager,
		private readonly TableMapper $tableMapper,
		private readonly ViewMapper $viewMapper,
		private NotificationHelper $notificationHelper,
		private LoggerInterface $logger,
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

		try {
			$view = $viewId ? $this->viewMapper->find($viewId) : null;
			$targetTableId = $view?->getTableId() ?? $tableId;
			if ($targetTableId === null) {
				$this->logger->error('Import job was scheduled without a table or view id, skipping.');
				return;
			}
			$table = $this->tableMapper->find($targetTableId);
		} catch (DoesNotExistException $e) {
			$this->logger->warning('Import skipped, table or view no longer exists: ' . $e->getMessage(), ['exception' => $e]);
			return;
		}

		$oldUser = $this->userSession->getUser();
		$importSuccess = false;

		try {
			$user = $this->userManager->get($userId);
			$this->userSession->setUser($user);

			$importType = $argument['user_file_path'] ? ImportService::IMPORT_TYPE_USER_FILE : ImportService::IMPORT_TYPE_APP_FILE;
			$path = $argument['user_file_path'] ?: $argument['import_file_name'];

			$importStats = $this->importService
				->importV2(
					$userId,
					$importType,
					$path,
					$tableId,
					$viewId,
					$argument['create_missing_columns'],
					$argument['columns_config']
				);
			$importSuccess = true;
		} catch (\Exception $e) {
			$this->logger->error('Import failed: ' . $e->getMessage(), ['exception' => $e]);
		} finally {
			$this->userSession->setUser($oldUser);
		}

		if ($importSuccess) {
			$this->activityManager->triggerEvent(
				objectType: ActivityManager::TABLES_OBJECT_TABLE,
				object: $table,
				subject: ActivityManager::SUBJECT_IMPORT_FINISHED,
				additionalParams: [
					'importStats' => $importStats,
				],
				author: $userId
			);
			$notifySubject = ActivityManager::SUBJECT_IMPORT_FINISHED;
		} else {
			$notifySubject = ActivityManager::SUBJECT_IMPORT_FAILED;
		}

		if ($view !== null) {
			$this->notificationHelper->sendNotification(
				objectType: ActivityManager::TABLES_OBJECT_VIEW,
				object: $view,
				subject: $notifySubject,
				author: $userId
			);
		} else {
			$this->notificationHelper->sendNotification(
				objectType: ActivityManager::TABLES_OBJECT_TABLE,
				object: $table,
				subject: $notifySubject,
				author: $userId
			);
		}
	}
}
