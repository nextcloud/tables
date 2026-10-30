<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\TableService;
use OCP\DB\Exception;
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
		private readonly ContextService $contextService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeUserDeletedEvent)) {
			return;
		}

		$this->logger->info('event "user deleted" was triggered, will try to delete all data for the user: ' . $event->getUser()->getUID() . ' (' . $event->getUser()->getDisplayName() . ')');

		// applications first, so deleting the tables does not detach them from applications that go anyway
		$this->deleteApplications($event->getUser()->getUID());

		try {
			$tables = $this->tableService->findAll($event->getUser()->getUID(), true, true, false);

			// delete tables
			$this->logger->debug('found ' . count($tables) . ' tables for the user');
			foreach ($tables as $table) {
				$this->tableService->delete($table->getId(), $event->getUser()->getUID());
			}
			$this->logger->debug('tables for the deleted user removed');
		} catch (InternalError) {
		}
	}

	private function deleteApplications(string $userId): void {
		try {
			$contexts = array_filter(
				$this->contextService->findAll($userId),
				static fn (Context $context): bool => $context->getOwnerType() === Application::OWNER_TYPE_USER && $context->getOwnerId() === $userId,
			);
		} catch (InternalError|Exception $e) {
			$this->logger->error('could not find the applications of the deleted user ' . $userId, ['exception' => $e]);
			return;
		}

		$this->logger->debug('found ' . count($contexts) . ' applications for the user');
		foreach ($contexts as $context) {
			try {
				$this->contextService->delete($context->getId(), $userId);
			} catch (NotFoundError|Exception $e) {
				$this->logger->error('could not delete application ' . $context->getId() . ' of the deleted user ' . $userId, ['exception' => $e]);
			}
		}
		$this->logger->debug('applications for the deleted user removed');
	}
}
