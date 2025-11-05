<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Activity;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Service\ShareService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\L10N\IFactory;
use OCP\Server;
use Psr\Log\LoggerInterface;

class ActivityManager {

	public const TABLES_OBJECT_TABLE = 'tables_table';
	public const TABLES_OBJECT_ROW = 'tables_row';

	public const SUBJECT_TABLE_CREATE = 'table_create';
	public const SUBJECT_TABLE_UPDATE = 'table_update';
	public const SUBJECT_TABLE_UPDATE_TITLE = 'table_update_title';
	public const SUBJECT_TABLE_UPDATE_DESCRIPTION = 'table_update_description';
	public const SUBJECT_TABLE_DELETE = 'table_delete';

	public const SUBJECT_ROW_CREATE = 'row_create';
	public const SUBJECT_ROW_UPDATE = 'row_update';
	public const SUBJECT_ROW_DELETE = 'row_delete';

	public function __construct(
		private readonly IManager $manager,
		private readonly IFactory $l10nFactory,
		private readonly TableMapper $tableMapper,
		private readonly ColumnMapper $columnMapper,
		private readonly ShareService $shareService,
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
		} elseif ($object instanceof Row2) {
			$objectTitle = '#' . $object->getId();
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
			'table' => $table
		];
		switch ($subject) {
			// No need to enhance parameters since entity already contains the required data
			case self::SUBJECT_TABLE_CREATE:
			case self::SUBJECT_TABLE_DELETE:
				break;
			case self::SUBJECT_TABLE_UPDATE_DESCRIPTION:
				$subjectParams['after'] = $additionalParams['after'] ?? null;
				break;
			case self::SUBJECT_TABLE_UPDATE_TITLE:
				$subjectParams['before'] = $additionalParams['before'] ?? null;
				break;
			case self::SUBJECT_ROW_CREATE:
			case self::SUBJECT_ROW_UPDATE:
			case self::SUBJECT_ROW_DELETE:
				$subjectParams['row'] = $object;
				break;
			default:
				throw new \Exception('Unknown subject for activity.');
				break;
		}

		if ($subject === self::SUBJECT_ROW_UPDATE) {
			$subjectParams['changeCols'] = [];
			foreach ($additionalParams['before'] as $index => $colData) {
				if ($additionalParams['after'][$index] === $colData) {
					continue; // No change, skip
				} else {
					try {
						$column = $this->columnMapper->find($colData['columnId']);
						$subjectParams['changeCols'][] = [
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

	private function sendToUsers(IEvent $event, $object) {
		if ($object instanceof Table) {
			$tableId = $object->getId();
			$owner = $object->getOwnership();
		} elseif ($object instanceof Row2) {
			$tableId = $object->getTableId();
			$owner = $this->tableMapper->find($tableId)->getOwnership();
		} else {
			Server::get(LoggerInterface::class)->error('Could not send activity notify. Invalid object.', (array)$object);
			return null;
		}

		$event->setAffectedUser($owner);
		$this->manager->publish($event);

		foreach ($this->shareService->findSharedWithUserIds($tableId, 'table') as $userId) {
			$event->setAffectedUser($userId);

			/** @noinspection DisconnectedForeachInstructionInspection */
			$this->manager->publish($event);
		}
	}

	public function getActivityFormat($language, $subjectIdentifier, $subjectParams = [], $ownActivity = false) {
		$subject = '';
		$l = $this->l10nFactory->get(Application::APP_ID, $language);

		switch ($subjectIdentifier) {
			case self::SUBJECT_TABLE_CREATE:
				$subject = $ownActivity ? $l->t('You have created a new table {table}'): $l->t('{user} has created a new table {table}');
				break;
			case self::SUBJECT_TABLE_DELETE:
				$subject = $ownActivity ? $l->t('You have deleted the table {table}') : $l->t('{user} has deleted the table {table}');
				break;
			case self::SUBJECT_TABLE_UPDATE_TITLE:
				$subject = $ownActivity ? $l->t('You have renamed the table {before} to {table}') : $l->t('{user} has renamed the table {before} to {table}');
				break;
			case self::SUBJECT_TABLE_UPDATE_DESCRIPTION:
				$subject = $ownActivity ? $l->t('You have updated the description of table {table} to {after}') : $l->t('{user} has updated the description of table {table} to {after}');
				break;
			case self::SUBJECT_ROW_CREATE:
				$subject = $ownActivity ? $l->t('You have created a new row {row} in table {table}') : $l->t('{user} has created a new row {row} in table {table}');
				break;
			case self::SUBJECT_ROW_UPDATE:
				$columns = '';
				$count = 1;
				foreach ($subjectParams['changeCols'] as $index => $changeCol) {
					$columns .= '{col-' . $changeCol['id'] . '}';
					if ($index < count($subjectParams['changeCols']) - 1) {
						$count++;
						$columns .= ', ';
					}
				}

				$subject = $ownActivity
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
				break;
			case self::SUBJECT_ROW_DELETE:
				$subject = $ownActivity ? $l->t('You have deleted the row {row} in table {table}') : $l->t('{user} has deleted the row {row} in table {table}');
				break;
			default:
				break;
		}

		return $subject;
	}
}
