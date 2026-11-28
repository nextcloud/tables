<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\BackgroundJob;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ArchiveCleanupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/**
 * Drops the archive overrides of users who lost access to a node.
 *
 * Deciding this per user costs a full permission resolution, so it runs out
 * of band: the triggering request (a share deletion, a group or team removal)
 * only records which users to check for which node.
 */
class CleanupArchiveOverridesJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly ArchiveCleanupService $archiveCleanupService,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param mixed $argument array{nodeType: int, nodeId: int, userIds: list<string>}
	 */
	protected function run($argument): void {
		if (!is_array($argument)) {
			$this->logger->warning('Cannot clean up archive overrides: invalid argument');
			return;
		}

		$nodeType = (int)($argument['nodeType'] ?? -1);
		$nodeId = (int)($argument['nodeId'] ?? 0);
		$userIds = $argument['userIds'] ?? [];

		if ($nodeId <= 0 || !is_array($userIds) || $userIds === []) {
			return;
		}
		if (!in_array($nodeType, [Application::NODE_TYPE_TABLE, Application::NODE_TYPE_CONTEXT], true)) {
			$this->logger->warning('Cannot clean up archive overrides: unsupported node type ' . $nodeType);
			return;
		}

		foreach ($userIds as $userId) {
			$this->archiveCleanupService->removeOverrideIfStale((string)$userId, $nodeType, $nodeId);
		}
	}
}
