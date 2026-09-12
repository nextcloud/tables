<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Circles\Events\CircleMemberRemovedEvent;
use OCA\Circles\Model\Member;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Service\ArchiveCleanupService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\UserRemovedEvent;
use Psr\Log\LoggerInterface;

/**
 * Removes per-user archive overrides when a user loses access to shared
 * tables or applications because their group or circle membership ended.
 *
 * @template-implements IEventListener<Event>
 */
class ArchiveCleanupListener implements IEventListener {
	public function __construct(
		private readonly ArchiveCleanupService $archiveCleanupService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserRemovedEvent) {
			$this->archiveCleanupService->cleanupAfterMembershipLoss(
				[$event->getUser()->getUID()],
				$event->getGroup()->getGID(),
				ShareReceiverType::GROUP,
			);
			return;
		}

		if ($event instanceof CircleMemberRemovedEvent) {
			$this->handleCircleMemberRemoved($event);
		}
	}

	private function handleCircleMemberRemoved(CircleMemberRemovedEvent $event): void {
		try {
			$member = $event->getMember();
			if ($member === null) {
				return;
			}

			if ($member->getUserType() === Member::TYPE_CIRCLE) {
				$basedOn = $member->getBasedOn();
				$members = $basedOn !== null ? $basedOn->getInheritedMembers() : [];
			} else {
				$members = [$member];
			}

			$userIds = [];
			foreach ($members as $affectedMember) {
				if ($affectedMember->getUserType() === Member::TYPE_USER) {
					$userIds[] = $affectedMember->getUserId();
				}
			}

			if ($userIds === []) {
				return;
			}

			$this->archiveCleanupService->cleanupAfterMembershipLoss(
				$userIds,
				$event->getCircle()->getSingleId(),
				ShareReceiverType::CIRCLE,
			);
		} catch (\Throwable $e) {
			$this->logger->warning('cleanup of archive overrides after circle member removal failed: ' . $e->getMessage(), [
				'exception' => $e,
			]);
		}
	}
}
