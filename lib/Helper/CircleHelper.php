<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use OCA\Circles\CirclesManager;
use OCA\Circles\Db\CircleRequest;
use OCA\Circles\Db\MemberRequest;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Tables\Errors\InternalError;
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
	private ?MemberRequest $memberRequest = null;
	private ?CircleRequest $circleRequest = null;
	private array $circleDisplayNames = [];
	private array $federatedUsers = [];
	private array $unreachableUserIds = [];

	/**
	 * @psalm-suppress UndefinedClass
	 */
	public function __construct(
		private readonly LoggerInterface $logger,
		IAppManager $appManager,
		private readonly IL10N $l10n,
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
		return $this->circleDisplayNames[$userId][$circleId] ??= $this->resolveCircleDisplayName($circleId, $userId);
	}

	private function resolveCircleDisplayName(string $circleId, string $userId): string {
		if (!$this->circlesEnabled || isset($this->unreachableUserIds[$userId])) {
			return $circleId;
		}

		try {
			$federatedUser = $this->federatedUsers[$userId] ??= $this->circlesManager->getFederatedUser($userId, Member::TYPE_USER);
			$this->circlesManager->startSession($federatedUser);

			$circle = $this->circlesManager->getCircle($circleId);
			return $circle ? ($circle->getDisplayName() ?: $circleId) : $circleId;
		} catch (Throwable $e) {
			if ($e->getCode() === 404) {
				return $this->l10n->t('Team not found');
			}

			$this->unreachableUserIds[$userId] = true;
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

		$circleIds = array_map(fn (Circle $circle) => $circle->getSingleId(), $this->getUserCircles($userId));
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

	public function circleExists(string $circleId, string $userId): bool {
		if (!$this->circlesEnabled) {
			return false;
		}

		$userCircleIds = $this->getCircleIdsForUser($userId);
		if ($userCircleIds === null) {
			return false;
		}
		return in_array($circleId, $userCircleIds, true);
	}

	public function findSuccessorUserId(string $circleId, string $excludeUserId): ?string {
		if (!$this->circlesEnabled) {
			throw new InternalError('Cannot find a table owner while Circles is unavailable');
		}

		try {
			// Do not replace the Circles session while a membership event is running.
			$circleRequest = $this->circleRequest ??= Server::get(CircleRequest::class);
			$circle = $circleRequest->getCircle($circleId);
			if ($circle->hasOwner() && $this->isEligibleSuccessor($circle->getOwner(), $excludeUserId)) {
				return $circle->getOwner()->getUserId();
			}

			$memberRequest = $this->memberRequest ??= Server::get(MemberRequest::class);
			$successor = null;
			$highestLevel = -1;
			foreach ($memberRequest->getMembers($circleId) as $member) {
				if (!$this->isEligibleSuccessor($member, $excludeUserId)) {
					continue;
				}
				if ($member->getLevel() > $highestLevel) {
					$successor = $member->getUserId();
					$highestLevel = $member->getLevel();
				}
			}
			return $successor;
		} catch (Throwable $e) {
			$this->logger->error('Could not find a successor for a team table', ['circleId' => $circleId, 'exception' => $e]);
			throw new InternalError('Could not find a successor for a team table', previous: $e);
		}
	}

	private function isEligibleSuccessor(Member $member, string $excludeUserId): bool {
		return $member->getUserType() === Member::TYPE_USER
			&& $member->isLocal()
			&& $member->getStatus() === Member::STATUS_MEMBER
			&& $member->getUserId() !== ''
			&& $member->getUserId() !== $excludeUserId;
	}

}
