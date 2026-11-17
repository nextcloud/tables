<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Circles\Model\Member;
use OCA\Tables\Service\TableService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class TeamOwnershipListener implements IEventListener {
	public function __construct(
		private readonly TableService $tableService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof CircleDestroyedEvent) {
			try {
				$this->tableService->deleteTeamOwnedTables($event->getCircle()->getSingleId());
			} catch (\Throwable $e) {
				$this->logger->error('Could not delete tables owned by a destroyed team: ' . $e->getMessage(), [
					'exception' => $e,
				]);
			}
		} elseif ($event instanceof RemovingCircleMemberEvent) {
			$member = $event->getMember();
			if ($member->getUserType() === Member::TYPE_USER && $member->isLocal()) {
				try {
					$this->tableService->handleTeamStewardDeparture($event->getCircle()->getSingleId(), $member->getUserId());
				} catch (\Throwable $e) {
					$this->logger->error('Could not update team-owned tables after a team membership change: ' . $e->getMessage(), [
						'exception' => $e,
					]);
				}
			}
		}
	}
}
