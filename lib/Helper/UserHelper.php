<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use OCA\Tables\Errors\InternalError;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UserHelper {
	public function __construct(private readonly IUserManager $userManager, private readonly LoggerInterface $logger, private readonly IGroupManager $groupManager)
    {
    }

	public function getUserDisplayName(string $userId): string {
		try {
			$user = $this->getUser($userId);
			return $user->getDisplayName() ?: $userId;
		} catch (InternalError) {
			$this->logger->info('no user given, will return userId');
			return $userId;
		}
	}

	/**
	 * @throws InternalError
	 */
	private function getUser(string $userId): IUser {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			return $user;
		}
		throw new InternalError('User not found for ' . $userId);
	}

	/**
	 * @param string $userId
	 * @return array|null
	 */
	public function getGroupIdsForUser(string $userId): ?array {
		try {
			$user = $this->getUser($userId);
			return $this->groupManager->getUserGroupIds($user);
		} catch (InternalError) {
			return null;
		}
	}
}
