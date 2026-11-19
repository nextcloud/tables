<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\TableService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event|UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private readonly TableService $tableService,
		private readonly ContextService $contextService,
		private readonly ContextMapper $contextMapper,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			return;
		}

		$userId = $event->getUser()->getUID();
		$this->logger->info('event "user deleted" was triggered, will try to delete all data for the user: ' . $userId . ' (' . $event->getUser()->getDisplayName() . ')');

		// applications first, so deleting the tables does not detach them from applications that go anyway
		$this->deleteApplications($userId);
		$this->deleteTables($userId);
	}

	private function deleteApplications(string $userId): void {
		try {
			$contextIds = $this->contextMapper->findIdsOwnedByUser($userId);
		} catch (\Throwable $e) {
			$this->logger->error('could not find the applications of the deleted user ' . $userId, ['exception' => $e]);
			return;
		}

		$this->logger->debug('found ' . count($contextIds) . ' applications for the user');
		foreach ($contextIds as $contextId) {
			try {
				$this->contextService->delete($contextId, $userId);
			} catch (\Throwable $e) {
				$this->logger->error('could not delete application ' . $contextId . ' of the deleted user ' . $userId, ['exception' => $e]);
			}
		}
	}

	private function deleteTables(string $userId): void {
		try {
			$tables = $this->tableService->findAll($userId, true, true, false);
		} catch (\Throwable $e) {
			$this->logger->error('could not find the tables of the deleted user ' . $userId, ['exception' => $e]);
			return;
		}

		$this->logger->debug('found ' . count($tables) . ' tables for the user');
		foreach ($tables as $table) {
			try {
				$this->tableService->delete($table->getId(), $userId);
			} catch (\Throwable $e) {
				$this->logger->error('could not delete table ' . $table->getId() . ' of the deleted user ' . $userId, ['exception' => $e]);
			}
		}
	}
}
