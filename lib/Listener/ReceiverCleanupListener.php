<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\ShareMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event|UserDeletedEvent|BeforeGroupDeletedEvent|CircleDestroyedEvent> */
class ReceiverCleanupListener implements IEventListener {
	public function __construct(
		private ShareMapper $shareMapper,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$this->cleanupByParticipant(ShareReceiverType::USER, $event->getUser()->getUID());
		} elseif ($event instanceof BeforeGroupDeletedEvent) {
			$this->cleanupByParticipant(ShareReceiverType::GROUP, $event->getGroup()->getGID());
		} elseif ($event instanceof CircleDestroyedEvent) {
			$this->cleanupByParticipant(ShareReceiverType::CIRCLE, $event->getCircle()->getSingleId());
		}
	}

	private function cleanupByParticipant(string $type, string $participant): void {
		try {
			$this->shareMapper->deleteByReceiver($participant, $type);
		} catch (\Throwable $e) {
			$this->logger->warning('cleanup table shares for deleted receiver has failed: ' . $e->getMessage(), [
				'exception' => $e,
				'receiver_type' => $type,
				'receiver' => $participant,
			]);
		}
	}
}
