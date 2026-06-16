<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Circles\CirclesManager;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Model\Member;
use OCA\Tables\Db\ShareMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\User\Events\UserDeletedEvent;

/** @template-implements IEventListener<Event|UserDeletedEvent|BeforeGroupDeletedEvent|CircleDestroyedEvent> */
class ReceiverCleanupListener implements IEventListener {
	public function __construct(
		private ShareMapper $shareMapper,
		private CirclesManager $circlesManager,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$this->cleanupByParticipant('user', $event->getUser()->getUID());
		} elseif ($event instanceof BeforeGroupDeletedEvent) {
			$this->cleanupGroupShares($event->getGroup()->getGID());
		} elseif ($event instanceof CircleDestroyedEvent) {
			$this->cleanupByParticipant('circle', $event->getCircle()->getSingleId());
		}
	}

	private function cleanupGroupShares(string $gid): void {
		$this->cleanupByParticipant('group', $gid);

		try {
			$singleId = $this->circlesManager
				->getFederatedUser($gid, Member::TYPE_GROUP)
				->getSingleId();
		} catch (\Throwable) {
			return;
		}

		$this->cleanupByParticipant('group', $singleId);
	}

	private function cleanupByParticipant(string $type, string $participant): void {
		$this->shareMapper->deleteByReceiver($participant, $type);
	}
}
