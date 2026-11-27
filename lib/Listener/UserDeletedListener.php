<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\TableService;
use OCP\DB\Exception;
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
		private readonly TableMapper $tableMapper,
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
		$applicationFailure = $this->deleteApplications($userId);
		$tableFailure = $this->deleteTables($userId);

		// the server keeps the user marked as deleted and runs the deletion again later
		$failure = $applicationFailure ?? $tableFailure;
		if ($failure !== null) {
			throw $failure;
		}
	}

	/**
	 * @return \Throwable|null the first failed deletion, after trying all applications
	 * @throws Exception when the applications cannot be looked up
	 */
	private function deleteApplications(string $userId): ?\Throwable {
		$contextIds = $this->contextMapper->findIdsOwnedByUser($userId);

		$this->logger->debug('found ' . count($contextIds) . ' applications for the user');
		$failure = null;
		foreach ($contextIds as $contextId) {
			try {
				$this->contextService->delete($contextId, $userId);
			} catch (\Throwable $e) {
				$this->logger->error('could not delete application ' . $contextId . ' of the deleted user ' . $userId, ['exception' => $e]);
				$failure ??= $e;
			}
		}
		return $failure;
	}

	/**
	 * @return \Throwable|null the first failed deletion, after trying all tables
	 * @throws Exception when the tables cannot be looked up
	 */
	private function deleteTables(string $userId): ?\Throwable {
		$tables = $this->tableMapper->findAll($userId);

		$this->logger->debug('found ' . count($tables) . ' tables for the user');
		$failure = null;
		foreach ($tables as $table) {
			try {
				$this->tableService->delete($table->getId(), $userId);
			} catch (\Throwable $e) {
				$this->logger->error('could not delete table ' . $table->getId() . ' of the deleted user ' . $userId, ['exception' => $e]);
				$failure ??= $e;
			}
		}
		return $failure;
	}
}
