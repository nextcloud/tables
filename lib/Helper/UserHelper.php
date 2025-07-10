<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use OCA\Tables\Errors\InternalError;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UserHelper {
	private IUserManager $userManager;

	private LoggerInterface $logger;

	private IGroupManager $groupManager;

	public function __construct(IUserManager $userManager, LoggerInterface $logger, IGroupManager $groupManager) {
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->groupManager = $groupManager;
	}

	public function getUserDisplayName(string $userId): string {
		try {
			$user = $this->getUser($userId);
			return $user->getDisplayName() ? $user->getDisplayName() : $userId;
		} catch (InternalError $e) {
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
	 * @return IGroup[]
	 * @throws InternalError
	 */
	public function getGroupsForUser(string $userId): array {
		$user = $this->getUser($userId);
		return $this->groupManager->getUserGroups($user);
	}

	/**
	 * @param string $userId
	 * @return array|null
	 */
	public function getGroupIdsForUser(string $userId): ?array {
		try {
			$userGroups = $this->getGroupsForUser($userId);
		} catch (InternalError $e) {
			return null;
		}

		$groupArray = array_map(function (IGroup $group) {
			return $group->getGID();
		}, $userGroups);
		return $groupArray;
	}
}
