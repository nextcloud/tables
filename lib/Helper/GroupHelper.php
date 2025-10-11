<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

class GroupHelper {
	private LoggerInterface $logger;
	private IGroupManager $groupManager;

	public function __construct(LoggerInterface $logger, IGroupManager $groupManager) {
		$this->logger = $logger;
		$this->groupManager = $groupManager;
	}

	public function getGroupDisplayName(string $groupId): string {
		if ($group = $this->groupManager->get($groupId)) {
			return $group->getDisplayName() ?: $groupId;
		} else {
			$this->logger->info('no group given, will return groupId');
			return $groupId;
		}
	}

	public function getUserIdsInGroup(string $groupId): array {
		$users = [];
		try {
			$group = $this->groupManager->get($groupId);
			if ($group) {
				foreach ($group->getUsers() as $user) {
					$users[] = $user->getUID();
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Error fetching users in group: ' . $e->getMessage());
		}
		return $users;
	}
}
