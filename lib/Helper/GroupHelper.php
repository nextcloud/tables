<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

class GroupHelper {
	private IGroupManager $groupManager;

	public function __construct(private LoggerInterface $logger, IGroupManager $groupManager) {
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
}
