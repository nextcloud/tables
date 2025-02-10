<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Service\TableService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\BeforeUserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event|BeforeUserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	private TableService $tableService;

	private LoggerInterface $logger;

	public function __construct(TableService $tableService, LoggerInterface $logger) {
		$this->tableService = $tableService;
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeUserDeletedEvent)) {
			return;
		}

		try {
			$tables = $this->tableService->findAll($event->getUser()->getUID(), true, true, false);
			$this->logger->info('event "user deleted" was triggered, will try to delete all data for the user: ' . $event->getUser()->getUID() . ' (' . $event->getUser()->getDisplayName() . ')');

			// delete tables
			$this->logger->debug('found ' . count($tables) . ' tables for the user');
			foreach ($tables as $table) {
				$this->tableService->delete($table->getId(), $event->getUser()->getUID());
			}
			$this->logger->debug('tables for the deleted user removed');
		} catch (InternalError $e) {
		}
	}
}
