<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Probes\CircleProbe;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\Server;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @psalm-suppress UndefinedClass
 */
class CircleHelper {
	private bool $circlesEnabled;
	private ?CirclesManager $circlesManager;

	/**
	 * @psalm-suppress UndefinedClass
	 */
	public function __construct(
		private LoggerInterface $logger,
		IAppManager $appManager,
		private IL10N $l10n,
	) {
		$this->circlesEnabled = $appManager->isEnabledForUser('circles');
		$this->circlesManager = null;

		if ($this->circlesEnabled) {
			try {
				$this->circlesManager = Server::get(CirclesManager::class);
			} catch (Throwable $e) {
				$this->logger->warning('Failed to get CirclesManager: ' . $e->getMessage());
				$this->circlesEnabled = false;
			}
		}
	}

	public function isCirclesEnabled(): bool {
		return $this->circlesEnabled;
	}

	public function getCircleDisplayName(string $circleId, string $userId): string {
		if (!$this->circlesEnabled) {
			return $circleId;
		}

		try {
			$federatedUser = $this->circlesManager->getFederatedUser($userId, Member::TYPE_USER);
			$this->circlesManager->startSession($federatedUser);

			$circle = $this->circlesManager->getCircle($circleId);
			return $circle ? ($circle->getDisplayName() ?: $circleId) : $circleId;
		} catch (Throwable $e) {
			if ($e->getCode() === 404) {
				return $this->l10n->t('Deleted circle %s.', [$circleId]);
			}

			$this->logger->warning('Failed to get circle display name: ' . $e->getMessage(), [
				'circleId' => $circleId,
				'userId' => $userId
			]);
			return $circleId;
		}
	}

	/**
	 * @param string $userId
	 * @return Circle[]
	 * @throws InternalError
	 */
	public function getUserCircles(string $userId): array {
		if (!$this->circlesEnabled) {
			return [];
		}

		try {
			$federatedUser = $this->circlesManager->getFederatedUser($userId, Member::TYPE_USER);
			$this->circlesManager->startSession($federatedUser);
			$probe = new CircleProbe();
			$probe->mustBeMember();
			return $this->circlesManager->getCircles($probe);
		} catch (Throwable $e) {
			$this->logger->warning('Failed to get user circles: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * @param string $userId
	 * @return array|null
	 */
	public function getCircleIdsForUser(string $userId): ?array {
		if (!$this->circlesEnabled) {
			return null;
		}

		$circleIds = array_map(function (Circle $circle) {
			return $circle->getSingleId();
		}, $this->getUserCircles($userId));
		return $circleIds;
	}

	public function getUserIdsInCircle(string $circleId): array {
		if (!$this->circlesEnabled || !$this->circlesManager) {
			return [];
		}

		try {
			$circle = $this->circlesManager->getCircle($circleId);
			if ($circle === null) {
				return [];
			}
			$members = $circle->getMembers();
			return array_map(static fn ($member) => $member->getUserId(), $members);
		} catch (Throwable $e) {
			$this->logger->warning('Failed to get users in circle: ' . $e->getMessage(), [
				'circleId' => $circleId
			]);
			return [];
		}
	}

}
