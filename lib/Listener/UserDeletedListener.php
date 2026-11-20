<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\TableService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\BeforeUserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event|BeforeUserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private readonly TableService $tableService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeUserDeletedEvent)) {
			return;
		}

		$userId = $event->getUser()->getUID();
		try {
			$tables = $this->tableService->findAll($userId, true, true, false);
			$this->logger->info('event "user deleted" was triggered, will try to delete all data for the user: ' . $userId . ' (' . $event->getUser()->getDisplayName() . ')');

			// delete tables
			$this->logger->debug('found ' . count($tables) . ' tables for the user');
			foreach ($tables as $table) {
				try {
					$teamId = $table->getTeamId();
					if ($teamId !== null) {
						$this->tableService->handleTeamStewardDeparture($teamId, $userId);
					} else {
						$this->tableService->delete($table->getId(), $userId);
					}
				} catch (InternalError|NotFoundError|PermissionError $e) {
					$this->logger->error('Could not clean up a table for a deleted user', [
						'tableId' => $table->getId(), 'userId' => $userId, 'exception' => $e,
					]);
				}
			}
			$this->logger->debug('tables for the deleted user removed');
		} catch (InternalError $e) {
			$this->logger->error('Could not delete the data of a deleted user: ' . $e->getMessage(), [
				'userId' => $userId,
				'exception' => $e,
			]);
		}
	}
}
