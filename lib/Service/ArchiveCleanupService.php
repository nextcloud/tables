<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\BackgroundJob\CleanupArchiveOverridesJob;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\UserArchiveMapper;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\ConversionHelper;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;

/**
 * Removes per-user archive overrides once a user loses access to a node.
 *
 * Overrides are pure UX metadata: a stale row has no effect for a user
 * without access (the entity flag fallback applies), so every method here
 * logs failures instead of throwing to never break the triggering flow.
 */
class ArchiveCleanupService {
	/**
	 * User ids per cleanup job. A job argument is serialised to JSON and
	 * refused beyond 4000 bytes; 50 ids stay far below that even for long
	 * user ids.
	 */
	private const JOB_USER_CHUNK_SIZE = 50;

	public function __construct(
		private readonly UserArchiveMapper $userArchiveMapper,
		private readonly ShareMapper $shareMapper,
		private readonly CircleHelper $circleHelper,
		private readonly PermissionsService $permissionsService,
		private readonly IJobList $jobList,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Remove archive overrides of the given users on all nodes shared with
	 * the given receiver, for every node the user can no longer access.
	 *
	 * Called when a user is removed from a group or from a circle.
	 *
	 * @param string[] $userIds
	 */
	public function cleanupAfterMembershipLoss(array $userIds, string $receiver, string $receiverType): void {
		try {
			$nodes = $this->shareMapper->findNodesByReceiver($receiver, $receiverType);
		} catch (\Throwable $e) {
			$this->logNonFatal(__FUNCTION__, $e);
			return;
		}

		$nodeIdsByType = [
			Application::NODE_TYPE_TABLE => [],
			Application::NODE_TYPE_CONTEXT => [],
		];
		foreach ($nodes as $node) {
			$nodeType = $this->shareNodeType2Const($node['nodeType']);
			if ($nodeType !== null) {
				$nodeIdsByType[$nodeType][] = $node['nodeId'];
			}
		}

		$affectedUsersByNode = [];
		foreach ($userIds as $userId) {
			foreach ($nodeIdsByType as $nodeType => $nodeIds) {
				if ($nodeIds === []) {
					continue;
				}
				try {
					$overrides = $this->userArchiveMapper->findAllOverridesForUser($userId, $nodeType, $nodeIds);
				} catch (\Throwable $e) {
					$this->logNonFatal(__FUNCTION__, $e);
					continue;
				}
				foreach (array_keys($overrides) as $nodeId) {
					$affectedUsersByNode[$nodeType][$nodeId][] = $userId;
				}
			}
		}

		foreach ($affectedUsersByNode as $nodeType => $usersByNodeId) {
			foreach ($usersByNodeId as $nodeId => $affectedUserIds) {
				$this->scheduleCleanup((int)$nodeType, (int)$nodeId, $affectedUserIds);
			}
		}
	}

	/**
	 * Remove the stale archive overrides of a node addressed by its share
	 * node-type string; share types without archive support (views) are
	 * ignored.
	 */
	public function purgeShareNodeOverrides(string $shareNodeType, int $nodeId): void {
		$nodeType = $this->shareNodeType2Const($shareNodeType);
		if ($nodeType === null) {
			return;
		}
		$this->purgeNodeOverrides($nodeType, $nodeId);
	}

	/**
	 * Remove the archive overrides of every user who can no longer access
	 * the given node.
	 *
	 * Called when a group or circle receiver of the node is deleted, or when
	 * a group or circle share of the node is removed.
	 */
	public function purgeNodeOverrides(int $nodeType, int $nodeId): void {
		try {
			$userIds = $this->userArchiveMapper->findUserIdsForNode($nodeType, $nodeId);
		} catch (\Throwable $e) {
			$this->logNonFatal(__FUNCTION__, $e);
			return;
		}

		$this->scheduleCleanup($nodeType, $nodeId, $userIds);
	}

	/**
	 * Hand the per-user access checks to a background job.
	 *
	 * Each check is a full permission resolution, so doing them inline would
	 * make an unrelated request (deleting one share, removing one member)
	 * scale with the number of users holding an override.
	 *
	 * @param list<string> $userIds
	 */
	private function scheduleCleanup(int $nodeType, int $nodeId, array $userIds): void {
		if ($userIds === []) {
			return;
		}

		// A job argument is stored as JSON and rejected above 4000 bytes, so a
		// widely shared node is split across several jobs rather than silently
		// failing to schedule.
		foreach (array_chunk(array_values(array_unique($userIds)), self::JOB_USER_CHUNK_SIZE) as $chunk) {
			try {
				$this->jobList->add(CleanupArchiveOverridesJob::class, [
					'nodeType' => $nodeType,
					'nodeId' => $nodeId,
					'userIds' => $chunk,
				]);
			} catch (\Throwable $e) {
				$this->logNonFatal(__FUNCTION__, $e);
			}
		}
	}

	/**
	 * Remove archive overrides that became stale because a share was deleted.
	 */
	public function cleanupAfterShareDeletion(Share $share): void {
		$nodeType = $this->shareNodeType2Const((string)$share->getNodeType());
		if ($nodeType === null) {
			return;
		}

		$receiverType = $share->getReceiverType();
		if ($receiverType === ShareReceiverType::USER) {
			$this->removeOverrideIfStale((string)$share->getReceiver(), $nodeType, (int)$share->getNodeId());
		} elseif ($receiverType === ShareReceiverType::GROUP || $receiverType === ShareReceiverType::CIRCLE) {
			$this->purgeNodeOverrides($nodeType, (int)$share->getNodeId());
		}
	}

	/**
	 * Remove every archive override of a deleted user account.
	 */
	public function cleanupDeletedUser(string $userId): void {
		try {
			$this->userArchiveMapper->deleteAllForUser($userId);
		} catch (\Throwable $e) {
			$this->logNonFatal(__FUNCTION__, $e);
		}
	}

	/**
	 * Drop a user's archive override for a node they can no longer reach.
	 *
	 * Team shares are taken into account, which plain context access checks do
	 * not do, so this is the only correct way to decide the question.
	 */
	public function removeOverrideIfStale(string $userId, int $nodeType, int $nodeId): void {
		try {
			if ($this->hasAccess($userId, $nodeType, $nodeId)) {
				return;
			}
			$this->userArchiveMapper->deleteForUser($userId, $nodeType, $nodeId);
		} catch (\Throwable $e) {
			$this->logNonFatal(__FUNCTION__, $e);
		}
	}

	private function hasAccess(string $userId, int $nodeType, int $nodeId): bool {
		if ($nodeType !== Application::NODE_TYPE_CONTEXT) {
			return $this->permissionsService->canAccessNodeById($nodeType, $nodeId, $userId);
		}
		if ($this->permissionsService->canAccessContextById($nodeId, $userId)) {
			return true;
		}

		// Context access resolves through ContextMapper, which only considers
		// the owner plus user and group shares, never team shares. Ask the
		// share table directly, otherwise a user whose only access is a team
		// share looks like they lost it and their override gets deleted.
		return $this->hasTeamShareFor($userId, $nodeId);
	}

	private function hasTeamShareFor(string $userId, int $nodeId): bool {
		$circleIds = $this->circleHelper->getCircleIdsForUser($userId);
		if (empty($circleIds)) {
			return false;
		}

		$shares = $this->shareMapper->findAllSharesForNodeTo('context', $nodeId, $userId, [], $circleIds);
		foreach ($shares as $share) {
			if ($share->getReceiverType() === ShareReceiverType::CIRCLE) {
				return true;
			}
		}
		return false;
	}

	private function shareNodeType2Const(string $nodeType): ?int {
		$nodeTypeConst = ConversionHelper::shareNodeType2Const($nodeType);
		if ($nodeTypeConst === null) {
			return null;
		}
		// Only tables and contexts can carry archive overrides; view shares
		// must not trigger pointless lookup or access-check queries.
		return in_array($nodeTypeConst, [Application::NODE_TYPE_TABLE, Application::NODE_TYPE_CONTEXT], true)
			? $nodeTypeConst
			: null;
	}

	private function logNonFatal(string $method, \Throwable $e): void {
		$this->logger->warning(static::class . ' - ' . $method . ': archive override cleanup failed: ' . $e->getMessage(), [
			'exception' => $e,
		]);
	}
}
