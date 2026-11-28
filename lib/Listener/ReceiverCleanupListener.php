<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\ContextNavigationMapper;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Service\ArchiveCleanupService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event|UserDeletedEvent|GroupDeletedEvent|CircleDestroyedEvent> */
class ReceiverCleanupListener implements IEventListener {
	public function __construct(
		private readonly ShareMapper $shareMapper,
		private readonly ContextNavigationMapper $contextNavigationMapper,
		private readonly ArchiveCleanupService $archiveCleanupService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$userId = $event->getUser()->getUID();
			$this->cleanupByParticipant(ShareReceiverType::USER, $userId);
			$this->cleanupNavigationOfUser($userId);
			$this->archiveCleanupService->cleanupDeletedUser($userId);
		} elseif ($event instanceof GroupDeletedEvent) {
			$this->cleanupReceiverGone(ShareReceiverType::GROUP, $event->getGroup()->getGID());
		} elseif ($event instanceof CircleDestroyedEvent) {
			$this->cleanupReceiverGone(ShareReceiverType::CIRCLE, $event->getCircle()->getSingleId());
		}
	}

	/**
	 * Delete all shares of a removed group or circle receiver and drop the
	 * archive overrides of every ex-member who lost access to the affected
	 * nodes with those shares.
	 */
	private function cleanupReceiverGone(string $type, string $participant): void {
		try {
			$nodes = $this->shareMapper->findNodesByReceiver($participant, $type);
		} catch (\Throwable $e) {
			$this->logger->warning('collecting nodes shared with deleted receiver has failed: ' . $e->getMessage(), [
				'exception' => $e,
				'receiver_type' => $type,
				'receiver' => $participant,
			]);
			$nodes = [];
		}

		$this->cleanupByParticipant($type, $participant);

		foreach ($nodes as $node) {
			$this->archiveCleanupService->purgeShareNodeOverrides((string)$node['nodeType'], $node['nodeId']);
		}
	}

	private function cleanupByParticipant(string $type, string $participant): void {
		$contextShareIds = [];
		try {
			$contextShareIds = $this->shareMapper->findContextShareIdsByReceiver($participant, $type);
		} catch (\Throwable $e) {
			$this->logger->warning('reading application shares of deleted receiver has failed: ' . $e->getMessage(), [
				'exception' => $e,
				'receiver_type' => $type,
				'receiver' => $participant,
			]);
		}

		try {
			$this->shareMapper->deleteByReceiver($participant, $type);
		} catch (\Throwable $e) {
			$this->logger->warning('cleanup table shares for deleted receiver has failed: ' . $e->getMessage(), [
				'exception' => $e,
				'receiver_type' => $type,
				'receiver' => $participant,
			]);
		}

		try {
			$this->contextNavigationMapper->deleteByShareIds($contextShareIds);
		} catch (\Throwable $e) {
			$this->logger->warning('cleanup application navigation of deleted receiver has failed: ' . $e->getMessage(), [
				'exception' => $e,
				'receiver_type' => $type,
				'receiver' => $participant,
			]);
		}
	}

	private function cleanupNavigationOfUser(string $userId): void {
		try {
			$this->contextNavigationMapper->deleteByUserId($userId);
		} catch (\Throwable $e) {
			$this->logger->warning('cleanup application navigation of deleted user has failed: ' . $e->getMessage(), [
				'exception' => $e,
				'user' => $userId,
			]);
		}
	}
}
