<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Notification;

use DateTime;
use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Constants\UsergroupType;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ConfigService;
use OCA\Tables\Service\ShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Server;
use Psr\Log\LoggerInterface;

class NotificationHelper {
	public function __construct(
		protected readonly IManager $notificationManager,
		private readonly ConfigService $configService,
		private readonly ShareService $shareService,
		private readonly TableMapper $tableMapper,
		private readonly ViewMapper $viewMapper,
		private readonly Row2Mapper $row2Mapper,
		private readonly ColumnMapper $columnMapper,
		protected readonly ActivityManager $activityManager,
		protected readonly ?string $userId,
	) {
	}

	/**
	 * @param Row2|Column $object
	 * @param string $subject
	 * @param array<string, mixed> $additionalParams
	 * @param string|null $author
	 */
	public function sendNotification(string $objectType, Row2|Column $object, string $subject, $additionalParams = [], ?string $author = null): void {
		try {
			switch ($objectType) {
				case ActivityManager::TABLES_OBJECT_ROW:
					if ($object instanceof Row2) {
						$this->sendRowNotification($object, $subject, $additionalParams, $author);
					}
					break;
				case ActivityManager::TABLES_OBJECT_COLUMN:
					if ($object instanceof Column) {
						$this->sendColumnNotification($object, $subject, $author);
					}
					break;
			}
		} catch (\Throwable $e) {
			// Notifications are best effort and must not block write operations.
			Server::get(LoggerInterface::class)->error('Failed to send notification for object type ' . $objectType, [
				'objectId' => is_object($object) && method_exists($object, 'getId') ? $object->getId() : null,
				'subject' => $subject,
				'error' => $e->getMessage(),
			]);
		}
	}

	/**
	 * @param string|null $author
	 */
	private function sendColumnNotification(Column $column, string $subject, ?string $author): void {
		if (!in_array($subject, [
			ActivityManager::SUBJECT_COLUMN_CREATE,
			ActivityManager::SUBJECT_COLUMN_UPDATE,
			ActivityManager::SUBJECT_COLUMN_DELETE,
		], true)) {
			return;
		}

		$columnId = $column->getId();
		$tableId = $column->getTableId();
		if ($columnId === null || $tableId === null) {
			return;
		}

		$table = $this->tableMapper->find($tableId);
		$authorId = is_string($author) && $author !== '' ? $author : $this->userId;

		$subjectParams = [
			'author' => $authorId,
			'objectType' => ActivityManager::TABLES_OBJECT_COLUMN,
			'table' => [
				'id' => $tableId,
				'title' => $table->getTitle(),
			],
			'column' => [
				'id' => $columnId,
				'title' => $column->getTitle(),
			],
		];

		$tableRecipients = $this->activityManager->findRecipientsByElement($table);

		foreach ($tableRecipients as $receiverId) {
			if (!is_string($receiverId) || $receiverId === '' || $receiverId === $authorId) {
				continue;
			}
			if (!$this->configService->isNotifyEnabledForScope($receiverId, 'table', $tableId, ConfigService::NOTIFY_COLUMN_KEY)) {
				continue;
			}

			$params = array_merge($subjectParams, ['isViewContext' => false]);
			$notification = $this->generateNotification(
				subject: $subject,
				subjectParams: $params,
				objectType: ActivityManager::TABLES_OBJECT_COLUMN,
				objectId: (string)$columnId,
				receiver: $receiverId,
			);
			$this->notificationManager->notify($notification);
		}

		foreach ($this->viewMapper->findAll($tableId) as $view) {
			if (!in_array($columnId, $view->getColumnIds(), true)) {
				continue;
			}

			$viewRecipients = $this->activityManager->findRecipientsByElement($view);

			foreach ($viewRecipients as $receiverId) {
				if (!is_string($receiverId) || $receiverId === '' || $receiverId === $authorId) {
					continue;
				}
				if (!$this->configService->isNotifyEnabledForScope($receiverId, 'view', $view->getId(), ConfigService::NOTIFY_COLUMN_KEY)) {
					continue;
				}

				$params = array_merge($subjectParams, [
					'isViewContext' => true,
					'view' => [
						'id' => $view->getId(),
						'title' => $view->getTitle(),
					],
				]);
				$notification = $this->generateNotification(
					subject: $subject,
					subjectParams: $params,
					objectType: ActivityManager::TABLES_OBJECT_COLUMN,
					objectId: (string)$columnId,
					receiver: $receiverId,
				);
				$this->notificationManager->notify($notification);
			}
		}
	}

	/**
	 * @param array<string, mixed> $additionalParams
	 * @param mixed $author
	 */
	private function sendRowNotification(Row2 $row, string $subject, array $additionalParams, $author): void {
		if (!in_array($subject, [
			ActivityManager::SUBJECT_ROW_CREATE,
			ActivityManager::SUBJECT_ROW_UPDATE,
			ActivityManager::SUBJECT_ROW_DELETE,
		], true)) {
			return;
		}

		$rowId = $row->getId();
		$tableId = $row->getTableId();
		if ($rowId === null || $tableId === null) {
			return;
		}

		$table = $this->tableMapper->find($tableId);
		$authorId = is_string($author) && $author !== '' ? $author : $this->userId;

		$subjectParams = [
			'author' => $authorId,
			'objectType' => ActivityManager::TABLES_OBJECT_ROW,
			'table' => [
				'id' => $tableId,
				'title' => $table->getTitle(),
			],
			'row' => [
				'id' => $rowId,
			],
		];

		if ($subject === ActivityManager::SUBJECT_ROW_UPDATE) {
			$subjectParams['changeCols'] = $this->resolveChangedColumns($additionalParams);
		}

		$this->sendNotifiesByElement(
			element: $table,
			subject: $subject,
			subjectParams: $subjectParams,
			objectType: ActivityManager::TABLES_OBJECT_ROW,
			objectId: (string)$rowId,
			authorId: $authorId,
			configKey: ConfigService::NOTIFY_ROW_KEY,
		);

		$assignedTargets = $subject === ActivityManager::SUBJECT_ROW_CREATE
			? $this->extractAssignedTargetsFromRowData($row->getData())
			: $this->extractAssignedTargetsFromChangedColumns($subjectParams['changeCols'] ?? []);

		foreach ($this->viewMapper->findAll($tableId) as $view) {
			if ($subject === ActivityManager::SUBJECT_ROW_UPDATE && !$this->viewContainsAnyChangedColumn($view, $subjectParams['changeCols'] ?? [])) {
				continue;
			}

			$this->sendNotifiesByElement(
				element: $view,
				subject: $subject,
				subjectParams: $subjectParams,
				objectType: ActivityManager::TABLES_OBJECT_ROW,
				objectId: (string)$rowId,
				authorId: $authorId,
				configKey: ConfigService::NOTIFY_ROW_KEY,
			);

			if (!empty($assignedTargets)) {
				$this->sendAssignedRowNotificationsToViewRecipients(
					rowId: $rowId,
					authorId: $authorId,
					subjectParams: $subjectParams,
					view: $view,
					recipients: $this->activityManager->findRecipientsByElement($view),
					assignedTargets: $assignedTargets,
				);
			}
		}

		if (!empty($assignedTargets)) {
			$this->sendAssignedRowNotificationsToTableRecipients(
				rowId: $rowId,
				tableId: $tableId,
				authorId: $authorId,
				subjectParams: $subjectParams,
				recipients: $this->activityManager->findRecipientsByElement($table),
				assignedTargets: $assignedTargets,
			);
		}
	}

	private function sendNotifiesByElement(Table|View $element, string $subject, array $subjectParams, string $objectType, string $objectId, ?string $authorId, ?string $configKey): void {
		$recipients = $this->activityManager->findRecipientsByElement($element);

		foreach ($recipients as $receiverId) {
			if (!is_string($receiverId) || $receiverId === '' || $receiverId === $authorId) {
				continue;
			}
			if ($configKey !== null && !$this->configService->isNotifyEnabledForScope($receiverId, $element instanceof Table ? 'table' : 'view', $element->getId(), $configKey)) {
				continue;
			}

			$params = array_merge($subjectParams, ['isViewContext' => $element instanceof View]);
			if ($element instanceof View) {
				$params['view'] = [
					'id' => $element->getId(),
					'title' => $element->getTitle(),
				];
			}

			$notification = $this->generateNotification(
				subject: $subject,
				subjectParams: $params,
				objectType: $objectType,
				objectId: $objectId,
				receiver: $receiverId,
			);
			$this->notificationManager->notify($notification);
		}
	}

	private function resolveChangedColumns(array $additionalParams): array {
		$changedColumns = [];

		if (!isset($additionalParams['before'], $additionalParams['after'])
			|| !is_array($additionalParams['before'])
			|| !is_array($additionalParams['after'])) {
			return $changedColumns;
		}

		$columnsCount = max(count($additionalParams['before']), count($additionalParams['after']));

		for ($i = 0; $i < $columnsCount; $i++) {
			$before = $additionalParams['before'][$i] ?? null;
			$after = $additionalParams['after'][$i] ?? null;
			$columnId = $before['columnId'] ?? $after['columnId'] ?? null;

			if ($before === $after) {
				continue; // No change, skip
			}

			try {
				$column = $this->columnMapper->find((int)$columnId);
				$changedColumns[] = [
					'id' => $column->getId(),
					'name' => $column->getTitle(),
					'type' => $column->getType(),
					'before' => $before,
					'after' => $after
				];
			} catch (DoesNotExistException|\Exception $e) {
				Server::get(LoggerInterface::class)->error('Could not find column for activity entry.', [
					'columnId' => $columnId,
					'exception' => $e->getMessage()
				]);
			}
		}

		return $changedColumns;
	}

	/**
	 * @param list<array{id: int, name: string}> $changedColumns
	 */
	private function viewContainsAnyChangedColumn(View $view, array $changedColumns): bool {
		if ($changedColumns === []) {
			return true;
		}

		$changedColumnIds = array_map(static fn (array $column): int => (int)$column['id'], $changedColumns);
		return !empty(array_intersect($changedColumnIds, $view->getColumnIds()));
	}

	private function generateNotification(string $subject, array $subjectParams, string $objectType, string $objectId, string $receiver): INotification {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('tables')
			->setUser($receiver)
			->setDateTime(new DateTime())
			->setObject($objectType, $objectId)
			->setSubject($subject, $subjectParams);
		return $notification;
	}

	/**
	 * @param list<array<string, mixed>> $rowData
	 * @return list<array{columnId: int, targetType: string, targetId: string, targetName: string, userIds: list<string>}>
	 */
	private function extractAssignedTargetsFromRowData(array $rowData): array {
		$assignedTargets = [];
		foreach ($rowData as $cell) {
			if (!is_array($cell) || !isset($cell['columnId'])) {
				continue;
			}

			$columnId = (int)$cell['columnId'];
			$column = $this->columnMapper->find($columnId);
			if ($column->getType() !== Column::TYPE_USERGROUP) {
				continue;
			}

			$assignedTargets = array_merge($assignedTargets, $this->resolveAssignedTargetsFromValue($columnId, $cell['value'] ?? null));
		}

		return $assignedTargets;
	}

	/**
	 * @param list<array<string, mixed>> $changedColumns
	 * @return list<array{columnId: int, targetType: string, targetId: string, targetName: string, userIds: list<string>}>
	 */
	private function extractAssignedTargetsFromChangedColumns(array $changedColumns): array {
		$assignedTargets = [];
		foreach ($changedColumns as $change) {
			if (!is_array($change) || ($change['type'] ?? null) !== Column::TYPE_USERGROUP) {
				continue;
			}

			$columnId = (int)($change['id'] ?? 0);
			if ($columnId <= 0) {
				continue;
			}

			$beforeEntries = $this->normalizeUsergroupEntries($change['before']['value'] ?? null);
			$afterEntries = $this->normalizeUsergroupEntries($change['after']['value'] ?? null);
			$newAssignments = $this->subtractUsergroupEntries($afterEntries, $beforeEntries);

			foreach ($newAssignments as $entry) {
				$target = $this->resolveAssignedTargetFromUsergroupEntry($columnId, $entry);
				if ($target !== null) {
					$assignedTargets[] = $target;
				}
			}
		}

		return $assignedTargets;
	}

	/**
	 * @return list<array{columnId: int, targetType: string, targetId: string, targetName: string, userIds: list<string>}>
	 */
	private function resolveAssignedTargetsFromValue(int $columnId, mixed $value): array {
		$targets = [];
		foreach ($this->normalizeUsergroupEntries($value) as $entry) {
			$target = $this->resolveAssignedTargetFromUsergroupEntry($columnId, $entry);
			if ($target !== null) {
				$targets[] = $target;
			}
		}
		return $targets;
	}

	/**
	 * @param array{id: string, type: int} $entry
	 * @return array{columnId: int, targetType: string, targetId: string, targetName: string, userIds: list<string>}|null
	 */
	private function resolveAssignedTargetFromUsergroupEntry(int $columnId, array $entry): ?array {
		$targetId = $entry['id'];
		if ($targetId === '') {
			return null;
		}

		if ($entry['type'] === UsergroupType::USER) {
			return [
				'columnId' => $columnId,
				'targetType' => 'user',
				'targetId' => $targetId,
				'targetName' => $targetId,
				'userIds' => [$targetId],
			];
		}

		if ($entry['type'] === UsergroupType::GROUP) {
			return [
				'columnId' => $columnId,
				'targetType' => 'group',
				'targetId' => $targetId,
				'targetName' => $targetId,
				'userIds' => $this->shareService->findUserIdsForShareReceiver(ShareReceiverType::GROUP, $targetId),
			];
		}

		if ($entry['type'] === UsergroupType::CIRCLE) {
			return [
				'columnId' => $columnId,
				'targetType' => 'team',
				'targetId' => $targetId,
				'targetName' => $targetId,
				'userIds' => $this->shareService->findUserIdsForShareReceiver(ShareReceiverType::CIRCLE, $targetId),
			];
		}

		return null;
	}

	/**
	 * @param mixed $value
	 * @return list<array{id: string, type: int}>
	 */
	private function normalizeUsergroupEntries(mixed $value): array {
		if (is_string($value)) {
			$value = json_decode($value, true);
		}

		if (!is_array($value)) {
			return [];
		}

		$entries = [];
		foreach ($value as $entry) {
			if (!is_array($entry) || !isset($entry['id'], $entry['type'])) {
				continue;
			}

			if (!is_string($entry['id']) || $entry['id'] === '') {
				continue;
			}

			if (!is_int($entry['type'])) {
				continue;
			}

			$entries[] = [
				'id' => $entry['id'],
				'type' => $entry['type'],
			];
		}

		return $entries;
	}

	/**
	 * @param list<array{id: string, type: int}> $entries
	 * @return array<string, true>
	 */
	private function buildUsergroupEntryKeySet(array $entries): array {
		$set = [];
		foreach ($entries as $entry) {
			$set[$entry['type'] . ':' . $entry['id']] = true;
		}
		return $set;
	}

	/**
	 * @param list<array{id: string, type: int}> $baseEntries
	 * @param list<array{id: string, type: int}> $entriesToRemove
	 * @return list<array{id: string, type: int}>
	 */
	private function subtractUsergroupEntries(array $baseEntries, array $entriesToRemove): array {
		$removeSet = $this->buildUsergroupEntryKeySet($entriesToRemove);
		$remaining = [];

		foreach ($baseEntries as $entry) {
			$key = $entry['type'] . ':' . $entry['id'];
			if (!isset($removeSet[$key])) {
				$remaining[] = $entry;
			}
		}

		return $remaining;
	}

	/**
	 * @param list<string> $recipients
	 * @param list<array{columnId: int, targetType: string, targetId: string, targetName: string, userIds: list<string>}> $assignedTargets
	 */
	private function sendAssignedRowNotificationsToTableRecipients(
		int $rowId,
		int $tableId,
		?string $authorId,
		array $subjectParams,
		array $recipients,
		array $assignedTargets,
	): void {
		$recipientSet = array_fill_keys(array_filter($recipients, static fn (mixed $id): bool => is_string($id) && $id !== ''), true);
		$sent = [];

		foreach ($assignedTargets as $target) {
			foreach (array_unique($target['userIds']) as $receiverId) {
				if ($receiverId === '' || $receiverId === $authorId || !isset($recipientSet[$receiverId])) {
					continue;
				}
				if (!$this->configService->isNotifyEnabledForScope($receiverId, 'table', $tableId, ConfigService::NOTIFY_ASSIGNED_KEY)) {
					continue;
				}

				$dedupeKey = $receiverId . ':table:' . $target['targetType'] . ':' . $target['targetId'];
				if (isset($sent[$dedupeKey])) {
					continue;
				}
				$sent[$dedupeKey] = true;

				$params = $this->buildAssignedSubjectParams($subjectParams, $target, false);
				$notification = $this->generateNotification(
					subject: ActivityManager::SUBJECT_ROW_ASSIGN,
					subjectParams: $params,
					objectType: ActivityManager::TABLES_OBJECT_ROW,
					objectId: (string)$rowId,
					receiver: $receiverId,
				);
				$this->notificationManager->notify($notification);
			}
		}
	}

	/**
	 * @param list<string> $recipients
	 * @param list<array{columnId: int, targetType: string, targetId: string, targetName: string, userIds: list<string>}> $assignedTargets
	 */
	private function sendAssignedRowNotificationsToViewRecipients(
		int $rowId,
		?string $authorId,
		array $subjectParams,
		View $view,
		array $recipients,
		array $assignedTargets,
	): void {
		$recipientSet = array_fill_keys(array_filter($recipients, static fn (mixed $id): bool => is_string($id) && $id !== ''), true);
		$sent = [];

		foreach ($assignedTargets as $target) {
			if (!in_array($target['columnId'], $view->getColumnIds(), true)) {
				continue;
			}

			foreach (array_unique($target['userIds']) as $receiverId) {
				if ($receiverId === '' || $receiverId === $authorId || !isset($recipientSet[$receiverId])) {
					continue;
				}
				if (!$this->configService->isNotifyEnabledForScope($receiverId, 'view', $view->getId(), ConfigService::NOTIFY_ASSIGNED_KEY)) {
					continue;
				}
				if (!$this->row2Mapper->isRowInViewPresent($rowId, $view, $receiverId)) {
					continue;
				}

				$dedupeKey = $receiverId . ':view:' . $view->getId() . ':' . $target['targetType'] . ':' . $target['targetId'];
				if (isset($sent[$dedupeKey])) {
					continue;
				}
				$sent[$dedupeKey] = true;

				$params = $this->buildAssignedSubjectParams($subjectParams, $target, true, $view);
				$notification = $this->generateNotification(
					subject: ActivityManager::SUBJECT_ROW_ASSIGN,
					subjectParams: $params,
					objectType: ActivityManager::TABLES_OBJECT_ROW,
					objectId: (string)$rowId,
					receiver: $receiverId,
				);
				$this->notificationManager->notify($notification);
			}
		}
	}

	/**
	 * @param array{columnId: int, targetType: string, targetId: string, targetName: string, userIds: list<string>} $target
	 */
	private function buildAssignedSubjectParams(array $subjectParams, array $target, bool $isViewContext, ?View $view = null): array {
		$params = array_merge($subjectParams, [
			'isViewContext' => $isViewContext,
			'assignedTargetType' => $target['targetType'],
			'assignedTargetId' => $target['targetId'],
			'assignedTargetName' => $target['targetName'],
		]);

		if ($isViewContext && $view !== null) {
			$params['view'] = [
				'id' => $view->getId(),
				'title' => $view->getTitle(),
			];
		}

		if ($target['targetType'] === 'group') {
			$params['group'] = [
				'id' => $target['targetId'],
				'name' => $target['targetName'],
			];
		}

		if ($target['targetType'] === 'team') {
			$params['team'] = [
				'id' => $target['targetId'],
				'name' => $target['targetName'],
			];
		}

		return $params;
	}
}
