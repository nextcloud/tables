<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\BackgroundJob;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\ImportService;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
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
		private readonly NotificationHelper $notificationHelper,
		private readonly LoggerInterface $logger,
		private readonly IL10N $l10n,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array{user_id: string,
	 *     table_id: integer|null,
	 *     view_id: integer|null,
	 *     user_file_path: string|null,
	 *     import_file_name: string|null,
	 *     original_file_name?: string|null,
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
		$importStats = null;
		$importErrorMessage = null;

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
					$argument['columns_config'],
					$argument['original_file_name'] ?? $argument['import_file_name'] ?? null
				);
			$importSuccess = true;
		} catch (\Exception $e) {
			$importErrorMessage = match (true) {
				$e instanceof NotFoundError, $e instanceof NotFoundException => $this->l10n->t('The import file or storage could not be found.'),
				$e instanceof PermissionError, $e instanceof NotPermittedException => $this->l10n->t('You do not have permission to import this file.'),
				$e instanceof BadRequestError, $e instanceof ReaderException => $this->l10n->t('The import file could not be read. Check its format and contents.'),
				default => $this->l10n->t('An unexpected error occurred during the import. Please contact your administrator.'),
			};
			$this->logger->error('Import failed: ' . $e->getMessage(), ['exception' => $e]);
		} finally {
			$this->userSession->setUser($oldUser);
		}

		if ($importSuccess) {
			$notifySubject = ActivityManager::SUBJECT_IMPORT_FINISHED;
			$notifyParams = [
				'importStats' => $importStats->withoutProblems(),
				'importProblemsFile' => $this->importService->getStoredProblemsFile(),
			];
		} else {
			$this->activityManager->triggerEvent(
				objectType: ActivityManager::TABLES_OBJECT_TABLE,
				object: $table,
				subject: ActivityManager::SUBJECT_IMPORT_FAILED,
				additionalParams: [
					'errorMessage' => $importErrorMessage,
				],
				author: $userId
			);
			$notifySubject = ActivityManager::SUBJECT_IMPORT_FAILED;
			$notifyParams = ['errorMessage' => $importErrorMessage];
		}

		if ($view !== null) {
			$this->notificationHelper->sendNotification(
				objectType: ActivityManager::TABLES_OBJECT_VIEW,
				object: $view,
				subject: $notifySubject,
				additionalParams: $notifyParams,
				author: $userId
			);
		} else {
			$this->notificationHelper->sendNotification(
				objectType: ActivityManager::TABLES_OBJECT_TABLE,
				object: $table,
				subject: $notifySubject,
				additionalParams: $notifyParams,
				author: $userId
			);
		}
	}
}
