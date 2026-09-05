<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\UserArchiveMapper;
use Psr\Log\LoggerInterface;

/**
 * Removes per-user archive overrides once a user loses access to a node.
 *
 * Overrides are pure UX metadata: a stale row has no effect for a user
 * without access (the entity flag fallback applies), so every method here
 * logs failures instead of throwing to never break the triggering flow.
 */
class ArchiveCleanupService {

	public function __construct(
		private readonly UserArchiveMapper $userArchiveMapper,
		private readonly ShareMapper $shareMapper,
		private readonly PermissionsService $permissionsService,
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
					$this->removeOverrideIfStale($userId, $nodeType, $nodeId);
				}
			}
		}
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

		foreach ($userIds as $userId) {
			$this->removeOverrideIfStale($userId, $nodeType, $nodeId);
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

	private function removeOverrideIfStale(string $userId, int $nodeType, int $nodeId): void {
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
		if ($nodeType === Application::NODE_TYPE_CONTEXT) {
			return $this->permissionsService->canAccessContextById($nodeId, $userId);
		}
		return $this->permissionsService->canAccessNodeById($nodeType, $nodeId, $userId);
	}

	private function shareNodeType2Const(string $nodeType): ?int {
		return match ($nodeType) {
			'table' => Application::NODE_TYPE_TABLE,
			'context' => Application::NODE_TYPE_CONTEXT,
			default => null,
		};
	}

	private function logNonFatal(string $method, \Throwable $e): void {
		$this->logger->warning(static::class . ' - ' . $method . ': archive override cleanup failed: ' . $e->getMessage(), [
			'exception' => $e,
		]);
	}
}
