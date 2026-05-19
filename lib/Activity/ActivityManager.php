<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Activity;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ShareService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Cache\CappedMemoryCache;
use OCP\L10N\IFactory;
use OCP\Server;
use Psr\Log\LoggerInterface;

class ActivityManager {

	public const TABLES_OBJECT_TABLE = 'tables_table';
	public const TABLES_OBJECT_VIEW = 'tables_view';
	public const TABLES_OBJECT_ROW = 'tables_row';
	public const TABLES_OBJECT_COLUMN = 'tables_column';

	public const SUBJECT_TABLE_CREATE = 'table_create';
	public const SUBJECT_TABLE_UPDATE = 'table_update';
	public const SUBJECT_TABLE_UPDATE_TITLE = 'table_update_title';
	public const SUBJECT_TABLE_UPDATE_DESCRIPTION = 'table_update_description';
	public const SUBJECT_TABLE_DELETE = 'table_delete';

	public const SUBJECT_VIEW_CREATE = 'view_create';
	public const SUBJECT_VIEW_UPDATE = 'view_update';
	public const SUBJECT_VIEW_UPDATE_TITLE = 'view_update_title';
	public const SUBJECT_VIEW_UPDATE_DESCRIPTION = 'view_update_description';
	public const SUBJECT_VIEW_DELETE = 'view_delete';

	public const SUBJECT_ROW_CREATE = 'row_create';
	public const SUBJECT_ROW_UPDATE = 'row_update';
	public const SUBJECT_ROW_DELETE = 'row_delete';
	public const SUBJECT_ROW_ASSIGN = 'row_assign';

	public const SUBJECT_COLUMN_CREATE = 'column_create';
	public const SUBJECT_COLUMN_UPDATE = 'column_update';
	public const SUBJECT_COLUMN_DELETE = 'column_delete';

	public const SUBJECT_IMPORT_FINISHED = 'import_finished';

	public const SUBJECT_SHARE_CREATE = 'share_create';
	public const SUBJECT_SHARE_UPDATE = 'share_update';
	public const SUBJECT_SHARE_DELETE = 'share_delete';

	public const RECEIVER_TYPE_UNKNOWN = 'unknown';
	public const RECEIVER_ID_UNKNOWN = 'unknown';

	public const EVENT_TYPE_SHARING = 'tables_sharing';
	public const EVENT_TYPE_TABLE_ROW = 'tables_row_table';
	public const EVENT_TYPE_VIEW_ROW = 'tables_row_view';
	public const EVENT_TYPE_TABLE_COLUMN = 'tables_column_table';
	public const EVENT_TYPE_VIEW_COLUMN = 'tables_column_view';

	public function __construct(
		private readonly IManager $manager,
		private readonly IFactory $l10nFactory,
		private readonly TableMapper $tableMapper,
		private readonly ViewMapper $viewMapper,
		private readonly ColumnMapper $columnMapper,
		private readonly ShareService $shareService,
		protected readonly CappedMemoryCache $cache,
		private readonly ?string $userId,
	) {
	}

	public function triggerEvent($objectType, $object, $subject, $additionalParams = [], $author = null) {
		if ($author === null) {
			$author = $this->userId;
		}

		try {
			$event = $this->createEvent($objectType, $object, $subject, $additionalParams, $author);

			if ($event !== null) {
				$this->sendToUsers($event, $object);
			}
		} catch (\Exception $e) {
			// Ignore exception for undefined activities on update events
		}
	}

	public function triggerUpdateEvents($objectType, ChangeSet $changeSet, $subject) {
		$previousEntity = $changeSet->getBefore();
		$entity = $changeSet->getAfter();
		$events = [];

		if ($previousEntity !== null) {
			foreach ($entity->getUpdatedFields() as $field => $value) {
				$getter = 'get' . ucfirst($field);
				$subjectComplete = $subject . '_' . $field;
				$changes = [
					'before' => $previousEntity->$getter(),
					'after' => $entity->$getter()
				];
				if ($changes['before'] !== $changes['after']) {
					try {
						$event = $this->createEvent($objectType, $entity, $subjectComplete, $changes);
						if ($event !== null) {
							$events[] = $event;
						}
					} catch (\Exception $e) {
						// Ignore exception for undefined activities on update events
					}
				}
			}
		} else {
			try {
				$events = [$this->createEvent($objectType, $entity, $subject)];
			} catch (\Exception $e) {
				// Ignore exception for undefined activities on update events
			}
		}

		foreach ($events as $event) {
			$this->sendToUsers($event, $entity);
		}
	}

	private function createEvent($objectType, $object, $subject, $additionalParams = [], $author = null) {
		if ($object instanceof Table) {
			$objectTitle = $object->getTitle();
			$table = $object;
		} elseif ($object instanceof View) {
			$objectTitle = $object->getTitle();
			$table = $this->tableMapper->find($object->getTableId());
		} elseif ($object instanceof Row2) {
			$objectTitle = '#' . $object->getId();
			$table = $this->tableMapper->find($object->getTableId());
		} elseif ($object instanceof Column) {
			$objectTitle = $object->getTitle();
			$table = $this->tableMapper->find($object->getTableId());
		} else {
			Server::get(LoggerInterface::class)->error('Could not create activity entry for ' . $subject . '. Invalid object.', (array)$object);
			return null;
		}

		/**
		 * Automatically fetch related details for subject parameters
		 * depending on the subject
		 */
		$eventType = 'tables';
		$subjectParams = [
			'author' => $author === null ? $this->userId : $author,
			'table' => $table,
			'objectType' => $objectType,
		];
		if ($object instanceof View) {
			$subjectParams['view'] = $object;
		}
		switch ($subject) {
			// No need to enhance parameters since entity already contains the required data
			case self::SUBJECT_TABLE_CREATE:
			case self::SUBJECT_TABLE_UPDATE:
			case self::SUBJECT_TABLE_DELETE:
			case self::SUBJECT_VIEW_CREATE:
			case self::SUBJECT_VIEW_UPDATE:
			case self::SUBJECT_VIEW_DELETE:
				break;
			case self::SUBJECT_TABLE_UPDATE_DESCRIPTION:
			case self::SUBJECT_VIEW_UPDATE_DESCRIPTION:
				$subjectParams['after'] = $additionalParams['after'] ?? null;
				break;
			case self::SUBJECT_TABLE_UPDATE_TITLE:
			case self::SUBJECT_VIEW_UPDATE_TITLE:
				$subjectParams['before'] = $additionalParams['before'] ?? null;
				break;
			case self::SUBJECT_ROW_CREATE:
			case self::SUBJECT_ROW_UPDATE:
			case self::SUBJECT_ROW_DELETE:
				$eventType = self::EVENT_TYPE_TABLE_ROW;
				$subjectParams['row'] = $object;
				break;
			case self::SUBJECT_COLUMN_CREATE:
			case self::SUBJECT_COLUMN_UPDATE:
			case self::SUBJECT_COLUMN_DELETE:
				$eventType = self::EVENT_TYPE_TABLE_COLUMN;
				$subjectParams['column'] = $object;
				break;
			case self::SUBJECT_SHARE_CREATE:
			case self::SUBJECT_SHARE_UPDATE:
			case self::SUBJECT_SHARE_DELETE:
				$eventType = self::EVENT_TYPE_SHARING;
				$subjectParams['sharedWith'] = $this->buildSharedWithParam($additionalParams['share'] ?? []);
				break;
			case self::SUBJECT_IMPORT_FINISHED:
				$subjectParams['importStats'] = $additionalParams['importStats'] ?? null;
				break;
			default:
				throw new \Exception(sprintf('Unknown subject "%s" for activity.', $subject));
		}

		if ($subject === self::SUBJECT_ROW_UPDATE) {
			$subjectParams['changeCols'] = $this->extractChangeColsData($additionalParams);
			unset($additionalParams['before'], $additionalParams['after']);
		}

		$event = $this->manager->generateEvent();
		$event->setApp('tables')
			->setType($eventType)
			->setAuthor($subjectParams['author'])
			->setObject($objectType, (int)$object->getId(), $objectTitle)
			->setSubject($subject, $subjectParams)
			->setTimestamp(time());

		return $event;
	}

	private function extractChangeColsData(array $additionalParams): array {
		$changeCols = [];
		foreach ($additionalParams['before'] as $index => $colData) {
			if ($additionalParams['after'][$index] === $colData) {
				continue; // No change, skip
			} else {
				try {
					$column = $this->columnMapper->find($colData['columnId']);
					$changeCols[] = [
						'id' => $column->getId(),
						'name' => $column->getTitle(),
						'before' => $colData,
						'after' => $additionalParams['after'][$index]
					];
				} catch (\Exception $e) {
					Server::get(LoggerInterface::class)->error('Could not find column for activity entry.', [
						'columnId' => $colData['columnId'],
						'exception' => $e->getMessage()
					]);
					continue; // Skip if column not found
				}
			}
		}
		return $changeCols;
	}

	/**
	 * @param mixed $share
	 * @return array{id: string, name: string, type: string}
	 */
	private function buildSharedWithParam(mixed $share): array {
		if ($share instanceof Share) {
			$receiverId = $share->getReceiver();
			$receiverType = $share->getReceiverType();
			$receiverName = $share->getReceiverDisplayName() ?: $receiverId;

			if ($receiverType === 'link') {
				$receiverName = 'public link';
				$receiverId = 'link';
			}

			return [
				'id' => (string)$receiverId,
				'name' => (string)$receiverName,
				'type' => (string)$receiverType,
			];
		}

		if (is_array($share)) {
			$receiverType = isset($share['receiverType']) ? (string)$share['receiverType'] : self::RECEIVER_TYPE_UNKNOWN;
			$receiverId = isset($share['receiver']) ? (string)$share['receiver'] : self::RECEIVER_ID_UNKNOWN;
			$receiverName = isset($share['receiverDisplayName']) ? (string)$share['receiverDisplayName'] : $receiverId;

			if ($receiverType === 'link') {
				$receiverName = 'public link';
				$receiverId = 'link';
			}

			return [
				'id' => $receiverId,
				'name' => $receiverName,
				'type' => $receiverType,
			];
		}

		return [
			'id' => self::RECEIVER_ID_UNKNOWN,
			'name' => self::RECEIVER_ID_UNKNOWN,
			'type' => self::RECEIVER_TYPE_UNKNOWN,
		];
	}

	private function sendToUsers(IEvent $event, $object) {
		// Handle share events with restricted recipients
		$subject = $event->getSubject();
		if (in_array($subject, [self::SUBJECT_SHARE_CREATE, self::SUBJECT_SHARE_UPDATE, self::SUBJECT_SHARE_DELETE], true)) {
			$this->sendShareEventToUsers($event, $object);
			return;
		}

		if ($object instanceof Table || $object instanceof View) {
			$uniqueAffectedUser = $this->findRecipientsByElement($object);
		} elseif ($object instanceof Row2 || $object instanceof Column) {
			$tableId = $object->getTableId();
			$table = $this->tableMapper->find($tableId);
			$uniqueAffectedUser = $this->findRecipientsByElement($table);
		} else {
			Server::get(LoggerInterface::class)->error('Could not send activity notify. Invalid object.', (array)$object);
			return;
		}

		foreach ($uniqueAffectedUser as $userId) {
			$event->setAffectedUser($userId);

			/** @noinspection DisconnectedForeachInstructionInspection */
			$this->manager->publish($event);
		}

		if ($object instanceof Row2) {
			$this->sendRowEventToViewAccessibleUsers(clone $event, $object);
		}

		if ($object instanceof Column) {
			$this->sendColumnEventToViewAccessibleUsers(clone $event, $object);
		}
	}

	private function sendRowEventToViewAccessibleUsers(IEvent $event, Row2 $object) {
		$subject = $event->getSubject();
		$subjectParams = $event->getSubjectParameters();
		$event->setType(self::EVENT_TYPE_VIEW_ROW);
		$subjectParams['isViewContext'] = true;

		$changedColumnIds = [];
		$tableId = $object->getTableId();
		$owner = $this->tableMapper->find($tableId)->getOwnership();

		if ($event->getSubject() === self::SUBJECT_ROW_UPDATE) {
			foreach ($event->getSubjectParameters()['changeCols'] ?? [] as $changeCol) {
				$changedColumnIds[] = $changeCol['id'];
			}
		}

		foreach ($this->viewMapper->findAll($tableId) as $view) {
			$viewContainsChangedColumn = empty($changedColumnIds)
				|| !empty(array_intersect($changedColumnIds, $view->getColumnIds()));

			// Only send update events for views that contain changed columns
			if (!$viewContainsChangedColumn) {
				continue;
			}

			$uniqueAffectedUser = $this->findRecipientsByElement($view);

			foreach ($uniqueAffectedUser as $userId) {
				$subjectParams['view'] = $view;
				$event->setSubject($subject, $subjectParams);
				$event->setAffectedUser($userId);
				$this->manager->publish($event);
			}
		}
	}

	private function sendColumnEventToViewAccessibleUsers(IEvent $event, Column $object): void {
		$subject = $event->getSubject();
		$subjectParams = $event->getSubjectParameters();
		$event->setType(self::EVENT_TYPE_VIEW_COLUMN);
		$subjectParams['isViewContext'] = true;

		$tableId = $object->getTableId();
		$owner = $this->tableMapper->find($tableId)->getOwnership();
		$affectedViews = $this->getAffectedViewsForColumn($object);

		foreach ($affectedViews as $view) {
			$uniqueAffectedUser = $this->findRecipientsByElement($view);

			foreach ($uniqueAffectedUser as $userId) {
				$subjectParams['view'] = $view;
				$event->setSubject($subject, $subjectParams);
				$event->setAffectedUser($userId);
				$this->manager->publish($event);
			}
		}
	}

	/**
	 * @return list<View>
	 */
	public function getAffectedViewsForColumn(Column $column): array {
		$affectedViews = [];

		foreach ($this->viewMapper->findAll($column->getTableId()) as $view) {
			if (in_array($column->getId(), $view->getColumnIds(), true)) {
				$affectedViews[] = $view;
			}
		}

		return $affectedViews;
	}

	/**
	 * Send share events only to: event author, table owner, and shared-with receiver users.
	 */
	private function sendShareEventToUsers(IEvent $event, $object): void {
		if (!$object instanceof Table && !$object instanceof View) {
			Server::get(LoggerInterface::class)->error('Could not send share activity. Invalid object type.', (array)$object);
			return;
		}

		$tableId = $object instanceof Table ? $object->getId() : $object->getTableId();
		$table = $object instanceof Table ? $object : $this->tableMapper->find($tableId);
		$tableOwner = $table->getOwnership();
		$eventAuthor = $event->getAuthor();
		$sharedWithParam = $event->getSubjectParameters()['sharedWith'] ?? null;

		$recipients = [];

		// Always notify table owner
		$recipients[$tableOwner] = true;

		// Always notify event author (if different from table owner)
		if ($eventAuthor) {
			$recipients[$eventAuthor] = true;
		}

		// Notify shared-with users for user/group/team/circle receivers
		if ($sharedWithParam && isset($sharedWithParam['type'], $sharedWithParam['id'])) {
			$receiverType = (string)$sharedWithParam['type'];
			$receiverId = (string)$sharedWithParam['id'];

			if ($receiverType === ShareReceiverType::USER) {
				$recipients[$receiverId] = true;
			} elseif ($receiverType === ShareReceiverType::GROUP || $receiverType === ShareReceiverType::CIRCLE) {
				foreach ($this->shareService->findUserIdsForShareReceiver($receiverType, $receiverId) as $userId) {
					$recipients[$userId] = true;
				}
			}
		}

		foreach (array_keys($recipients) as $userId) {
			$this->setShareSubjectForRecipient($event, $userId);
			$event->setAffectedUser($userId);

			/** @noinspection DisconnectedForeachInstructionInspection */
			$this->manager->publish($event);
		}
	}

	private function setShareSubjectForRecipient(IEvent $event, string $recipientUserId): void {
		$subject = $event->getSubject();
		$subjectParams = $event->getSubjectParameters();

		$sharedWith = $subjectParams['sharedWith'] ?? null;
		$isDirectSharedWithUser = is_array($sharedWith)
			&& ($sharedWith['type'] ?? null) === ShareReceiverType::USER
			&& isset($sharedWith['id'])
			&& (string)$sharedWith['id'] === $recipientUserId;

		if ($isDirectSharedWithUser) {
			$subjectParams['sharedWithYou'] = true;
		} else {
			unset($subjectParams['sharedWithYou']);
		}

		$event->setSubject($subject, $subjectParams);
	}

	/**
	 * @param array<string, mixed> $subjectParams
	 * @return list<array{id: int, name: string, before: mixed, after: mixed}>
	 */
	private function getVisibleChangeCols(array $subjectParams): array {
		$changeCols = [];
		foreach ($subjectParams['changeCols'] ?? [] as $changeCol) {
			if (!is_array($changeCol)
				|| !isset($changeCol['id'], $changeCol['name'], $changeCol['before'], $changeCol['after'])
				|| !is_int($changeCol['id'])
				|| !is_string($changeCol['name'])) {
				continue;
			}

			$changeCols[] = [
				'id' => $changeCol['id'],
				'name' => $changeCol['name'],
				'before' => $changeCol['before'],
				'after' => $changeCol['after'],
			];
		}

		// If the activity is not in a view context, we return all change columns since they are all visible in the table context.
		if (!isset($subjectParams['view']) && isset($subjectParams['table'])) {
			return $changeCols;
		}

		$viewColumnIds = $this->getViewColumnIds($subjectParams['view']);
		if (empty($viewColumnIds)) {
			return $changeCols;
		}

		$visibleChangeCols = [];
		foreach ($changeCols as $changeCol) {
			if (in_array($changeCol['id'], $viewColumnIds, true)) {
				$visibleChangeCols[] = $changeCol;
			}
		}

		return $visibleChangeCols;
	}

	/**
	 * @param mixed $view
	 * @return int[]
	 */
	private function getViewColumnIds(mixed $view): array {
		if ($view instanceof View) {
			return $view->getColumnIds();
		}

		if (!is_array($view) || empty($view['columnSettings']) || !is_array($view['columnSettings'])) {
			return [];
		}

		$columnSettings = $view['columnSettings'];
		$columnIds = [];

		foreach ($columnSettings as $columnSetting) {
			if (!is_array($columnSetting) || !isset($columnSetting['columnId']) || !is_int($columnSetting['columnId'])) {
				continue;
			}

			$columnIds[] = $columnSetting['columnId'];
		}

		return $columnIds;
	}

	public function getActivitySubject($language, $subjectIdentifier, $subjectParams = [], $ownActivity = false) {
		$l = $this->l10nFactory->get(Application::APP_ID, $language);
		$isViewContext = $subjectParams['isViewContext'] ?? false;
		$isViewObject = ($subjectParams['objectType'] ?? null) === self::TABLES_OBJECT_VIEW;
		$sharedWith = $subjectParams['sharedWith'] ?? null;

		$subject = $this->formatTableActivity($l, $subjectIdentifier, $ownActivity);
		if ($subject !== null) {
			return $subject;
		}

		$subject = $this->formatViewActivity($l, $subjectIdentifier, $ownActivity);
		if ($subject !== null) {
			return $subject;
		}

		$subject = $this->formatRowActivity($l, $subjectIdentifier, $subjectParams, $ownActivity, $isViewContext);
		if ($subject !== null) {
			return $subject;
		}

		$subject = $this->formatColumnActivity($l, $subjectIdentifier, $ownActivity, $isViewContext);
		if ($subject !== null) {
			return $subject;
		}

		$subject = $this->formatShareActivity($l, $subjectIdentifier, $subjectParams, $ownActivity, $isViewObject, $sharedWith);
		if ($subject !== null) {
			return $subject;
		}

		if ($subjectIdentifier === self::SUBJECT_IMPORT_FINISHED) {
			return $ownActivity ? $l->t('You have imported file to table {table}') : $l->t('{user} has imported file to table {table}');
		}

		return '';
	}

	private function formatTableActivity($l, string $subjectIdentifier, bool $ownActivity): ?string {
		return match ($subjectIdentifier) {
			self::SUBJECT_TABLE_CREATE => $ownActivity ? $l->t('You have created a new table {table}') : $l->t('{user} has created a new table {table}'),
			self::SUBJECT_TABLE_UPDATE => $ownActivity ? $l->t('You have updated the table {table}') : $l->t('{user} has updated the table {table}'),
			self::SUBJECT_TABLE_DELETE => $ownActivity ? $l->t('You have deleted the table {table}') : $l->t('{user} has deleted the table {table}'),
			self::SUBJECT_TABLE_UPDATE_TITLE => $ownActivity ? $l->t('You have renamed the table {before} to {table}') : $l->t('{user} has renamed the table {before} to {table}'),
			self::SUBJECT_TABLE_UPDATE_DESCRIPTION => $ownActivity ? $l->t('You have updated the description of table {table} to {after}') : $l->t('{user} has updated the description of table {table} to {after}'),
			default => null,
		};
	}

	private function formatViewActivity($l, string $subjectIdentifier, bool $ownActivity): ?string {
		return match ($subjectIdentifier) {
			self::SUBJECT_VIEW_CREATE => $ownActivity ? $l->t('You have created a new view {view} in table {table}') : $l->t('{user} has created a new view {view} in table {table}'),
			self::SUBJECT_VIEW_UPDATE => $ownActivity ? $l->t('You have updated the view {view} in table {table}') : $l->t('{user} has updated the view {view} in table {table}'),
			self::SUBJECT_VIEW_DELETE => $ownActivity ? $l->t('You have deleted the view {view} from table {table}') : $l->t('{user} has deleted the view {view} from table {table}'),
			self::SUBJECT_VIEW_UPDATE_TITLE => $ownActivity ? $l->t('You have renamed the view {before} to {view} in table {table}') : $l->t('{user} has renamed the view {before} to {view} in table {table}'),
			self::SUBJECT_VIEW_UPDATE_DESCRIPTION => $ownActivity ? $l->t('You have updated the description of view {view} to {after} in table {table}') : $l->t('{user} has updated the description of view {view} to {after} in table {table}'),
			default => null,
		};
	}

	private function formatRowActivity($l, string $subjectIdentifier, array $subjectParams, bool $ownActivity, bool $isViewContext): ?string {
		switch ($subjectIdentifier) {
			case self::SUBJECT_ROW_CREATE:
				if ($isViewContext) {
					return $ownActivity ? $l->t('You have created a new row {row} in view {view}') : $l->t('{user} has created a new row {row} in view {view}');
				}

				return $ownActivity ? $l->t('You have created a new row {row} in table {table}') : $l->t('{user} has created a new row {row} in table {table}');
			case self::SUBJECT_ROW_UPDATE:
				return $this->formatRowUpdateActivity($l, $subjectParams, $ownActivity, $isViewContext);
			case self::SUBJECT_ROW_DELETE:
				if ($isViewContext) {
					return $ownActivity ? $l->t('You have deleted the row {row} in view {view}') : $l->t('{user} has deleted the row {row} in view {view}');
				}

				return $ownActivity ? $l->t('You have deleted the row {row} in table {table}') : $l->t('{user} has deleted the row {row} in table {table}');
			default:
				return null;
		}
	}

	private function formatRowUpdateActivity($l, array $subjectParams, bool $ownActivity, bool $isViewContext): string {
		$visibleChangeCols = $this->getVisibleChangeCols($subjectParams);
		$columns = '';
		$count = 1;

		foreach ($visibleChangeCols as $index => $changeCol) {
			$columns .= '{col-' . $changeCol['id'] . '}';
			if ($index < count($visibleChangeCols) - 1) {
				$count++;
				$columns .= ', ';
			}
		}

		if ($isViewContext) {
			return $ownActivity
				? $l->n(
					'You have updated cell %1$s on row {row} in view {view}',
					'You have updated cells %1$s on row {row} in view {view}',
					$count,
					[$columns],
				)
				: $l->n(
					'{user} has updated cell %1$s on row {row} in view {view}',
					'{user} has updated cells %1$s on row {row} in view {view}',
					$count,
					[$columns],
				);
		}

		return $ownActivity
			? $l->n(
				'You have updated cell %1$s on row {row} in table {table}',
				'You have updated cells %1$s on row {row} in table {table}',
				$count,
				[$columns],
			)
			: $l->n(
				'{user} has updated cell %1$s on row {row} in table {table}',
				'{user} has updated cells %1$s on row {row} in table {table}',
				$count,
				[$columns],
			);
	}

	private function formatColumnActivity($l, string $subjectIdentifier, bool $ownActivity, bool $isViewContext): ?string {
		if ($isViewContext) {
			return match ($subjectIdentifier) {
				self::SUBJECT_COLUMN_CREATE => $ownActivity ? $l->t('You have created a new column {column} in view {view}') : $l->t('{user} has created a new column {column} in view {view}'),
				self::SUBJECT_COLUMN_UPDATE => $ownActivity ? $l->t('You have updated the column {column} in view {view}') : $l->t('{user} has updated the column {column} in view {view}'),
				self::SUBJECT_COLUMN_DELETE => $ownActivity ? $l->t('You have deleted the column {column} from view {view}') : $l->t('{user} has deleted the column {column} from view {view}'),
				default => null,
			};
		}

		return match ($subjectIdentifier) {
			self::SUBJECT_COLUMN_CREATE => $ownActivity ? $l->t('You have created a new column {column} in table {table}') : $l->t('{user} has created a new column {column} in table {table}'),
			self::SUBJECT_COLUMN_UPDATE => $ownActivity ? $l->t('You have updated the column {column} in table {table}') : $l->t('{user} has updated the column {column} in table {table}'),
			self::SUBJECT_COLUMN_DELETE => $ownActivity ? $l->t('You have deleted the column {column} from table {table}') : $l->t('{user} has deleted the column {column} from table {table}'),
			default => null,
		};
	}

	private function formatShareActivity($l, string $subjectIdentifier, array $subjectParams, bool $ownActivity, bool $isViewObject, mixed $sharedWith): ?string {
		$sharedWithYou = $this->isSharedWithYou($subjectParams, $ownActivity);
		$isLinkShare = $this->isLinkShare($sharedWith);

		switch ($subjectIdentifier) {
			case self::SUBJECT_SHARE_CREATE:
				if ($isViewObject) {
					if ($sharedWithYou) {
						return $l->t('{user} has shared view {view} with you');
					}

					if ($isLinkShare) {
						return $ownActivity ? $l->t('You have shared the view {view} as public link') : $l->t('{user} has shared the view {view} as public link');
					}

					return $ownActivity ? $l->t('You have shared the view {view} with {sharedWith}') : $l->t('{user} has shared the view {view} with {sharedWith}');
				}

				if ($sharedWithYou) {
					return $l->t('{user} has shared table {table} with you');
				}

				if ($isLinkShare) {
					return $ownActivity ? $l->t('You have shared the table {table} as public link') : $l->t('{user} has shared the table {table} as public link');
				}

				return $ownActivity ? $l->t('You have shared the table {table} with {sharedWith}') : $l->t('{user} has shared the table {table} with {sharedWith}');
			case self::SUBJECT_SHARE_UPDATE:
				if ($isViewObject) {
					if ($sharedWithYou) {
						return $l->t('{user} has updated sharing for the view {view} with you');
					}

					if ($isLinkShare) {
						return $ownActivity ? $l->t('You have updated public link sharing for the view {view}') : $l->t('{user} has updated public link sharing for the view {view}');
					}

					return $ownActivity ? $l->t('You have updated sharing for the view {view} with {sharedWith}') : $l->t('{user} has updated sharing for the view {view} with {sharedWith}');
				}

				if ($sharedWithYou) {
					return $l->t('{user} has updated sharing for the table {table} with you');
				}

				if ($isLinkShare) {
					return $ownActivity ? $l->t('You have updated public link sharing for the table {table}') : $l->t('{user} has updated public link sharing for the table {table}');
				}

				return $ownActivity ? $l->t('You have updated sharing for the table {table} with {sharedWith}') : $l->t('{user} has updated sharing for the table {table} with {sharedWith}');
			case self::SUBJECT_SHARE_DELETE:
				if ($isViewObject) {
					if ($sharedWithYou) {
						return $l->t('{user} has removed sharing for the view {view} with you');
					}

					if ($isLinkShare) {
						return $ownActivity ? $l->t('You have removed public link sharing for the view {view}') : $l->t('{user} has removed public link sharing for the view {view}');
					}

					return $ownActivity ? $l->t('You have removed sharing for the view {view} with {sharedWith}') : $l->t('{user} has removed sharing for the view {view} with {sharedWith}');
				}

				if ($sharedWithYou) {
					return $l->t('{user} has removed sharing for the table {table} with you');
				}

				if ($isLinkShare) {
					return $ownActivity ? $l->t('You have removed public link sharing for the table {table}') : $l->t('{user} has removed public link sharing for the table {table}');
				}

				return $ownActivity ? $l->t('You have removed sharing for the table {table} with {sharedWith}') : $l->t('{user} has removed sharing for the table {table} with {sharedWith}');
			default:
				return null;
		}
	}

	private function isSharedWithYou(array $subjectParams, bool $ownActivity): bool {
		return !$ownActivity && ($subjectParams['sharedWithYou'] ?? false) === true;
	}

	private function isLinkShare(mixed $sharedWith): bool {
		return is_array($sharedWith) && ($sharedWith['type'] ?? null) === ShareReceiverType::LINK;
	}

	public function getActivityMessage($language, $subjectIdentifier) {
		$l = $this->l10nFactory->get(Application::APP_ID, $language);

		switch ($subjectIdentifier) {
			case self::SUBJECT_IMPORT_FINISHED:
				$lines = [
					$l->t('Found columns: {foundColumnsCount}'),
					$l->t('Matching columns: {matchingColumnsCount}'),
					$l->t('Created columns: {createdColumnsCount}'),
					$l->t('Inserted rows: {insertedRowsCount}'),
					$l->t('Updated rows: {updatedRowsCount}'),
					$l->t('Value parsing errors: {errorsParsingCount}'),
					$l->t('Row creation errors: {errorsCount}'),
				];
				return implode("\n", $lines);
			default:
				return null;
		}
	}

	public function findRecipientsByElement(Table|View $element): array {
		$cacheKey = 'element_recipients_' . $element->getId();
		$cached = $this->cache->get($cacheKey);
		if ($cached !== null) {
			return $cached;
		}

		$recipients = [];
		$owner = $element->getOwnership();

		if (is_string($owner) && $owner !== '') {
			$recipients[] = $owner;
		}

		if ($element instanceof View) {
			$recipients = array_merge($recipients, $this->shareService->findSharedWithUserIds($element->getId(), 'view'));
		}
		if ($element instanceof Table) {
			$recipients = array_merge($recipients, $this->shareService->findSharedWithUserIds($element->getId(), 'table'));
		}

		$recipients = array_unique($recipients);
		$this->cache->set($cacheKey, $recipients);
		return $recipients;
	}
}
