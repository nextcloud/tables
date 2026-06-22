<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\ShareReview;

use OCA\ShareReview\Sources\ISource;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Share\Events\ShareReviewAccessCheckEvent;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class ShareReviewSource implements ISource {

	private const NODE_TYPE_TABLE = 'table';
	private const NODE_TYPE_VIEW = 'view';
	private const NODE_TYPE_CONTEXT = 'context';

	private const RECEIVER_TYPE_LINK = 'link';

	public function __construct(
		private ShareMapper $shareMapper,
		private TableMapper $tableMapper,
		private ViewMapper $viewMapper,
		private ContextMapper $contextMapper,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private readonly ShareService $shareService,
		private readonly IEventDispatcher $eventDispatcher,
	) {
	}

	public function getName(): string {
		return $this->l10n->t('Tables');
	}

	/**
	 * @return list<array{id: int, object: string, initiator: string, type: int, recipient: string, permissions: int, password: bool, time: string, action: string}>
	 */
	public function getShares(): array {
		try {
			$rawShares = $this->shareMapper->findAllRaw();
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch shares: {message}', ['message' => $e->getMessage()]);
			return [];
		}

		$idsByType = $this->groupIdsByNodeType($rawShares);

		try {
			$tableNames = $this->tableMapper->findIdToTitleMap($idsByType[self::NODE_TYPE_TABLE]);
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch table names: {message}', ['message' => $e->getMessage()]);
			$tableNames = [];
		}

		try {
			$viewNames = $this->viewMapper->findIdToTitleMap($idsByType[self::NODE_TYPE_VIEW]);
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch view names: {message}', ['message' => $e->getMessage()]);
			$viewNames = [];
		}

		try {
			$contextNames = $this->contextMapper->findIdToNameMap($idsByType[self::NODE_TYPE_CONTEXT]);
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch application names: {message}', ['message' => $e->getMessage()]);
			$contextNames = [];
		}

		$formatted = [];
		foreach ($rawShares as $share) {
			$formatted[] = $this->buildShareInfo($share, $tableNames, $viewNames, $contextNames)->toArray();
		}
		return $formatted;
	}

	public function deleteShare(string $shareId): bool {
		if (!is_numeric($shareId)) {
			return false;
		}

		$event = new ShareReviewAccessCheckEvent('Tables', $shareId);
		$this->eventDispatcher->dispatchTyped($event);

		if (!$event->isHandled() || !$event->isGranted()) {
			return false;
		}

		try {
			$this->shareService->deleteForShareReview((int)$shareId);
			return true;
		} catch (DoesNotExistException) {
			return false;
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to delete share {id}: {message}', ['id' => $shareId, 'message' => $e->getMessage()]);
			return false;
		}
	}

	/**
	 * @param array<string, mixed> $share
	 * @param array<int, string> $tableNames
	 * @param array<int, string> $viewNames
	 * @param array<int, string> $contextNames
	 */
	private function buildShareInfo(array $share, array $tableNames, array $viewNames, array $contextNames): ShareInfo {
		return new ShareInfo(
			id: (int)$share['id'],
			object: $this->resolveObjectName($share, $tableNames, $viewNames, $contextNames),
			initiator: (string)$share['sender'],
			type: $this->mapReceiverType((string)$share['receiver_type']),
			recipient: $share['receiver_type'] === self::RECEIVER_TYPE_LINK
				? (string)$share['token']
				: (string)$share['receiver'],
			permissions: $this->computePermissions($share),
			password: $share['password'] !== null,
			time: $this->resolveShareTime($share),
		);
	}

	/**
	 * Group distinct node IDs by node type in a single pass over the share list.
	 *
	 * @param list<array<string, mixed>> $shares
	 * @return array{table: list<int>, view: list<int>, context: list<int>}
	 */
	private function groupIdsByNodeType(array $shares): array {
		$tableIds = [];
		$viewIds = [];
		$contextIds = [];
		foreach ($shares as $share) {
			$nodeId = (int)$share['node_id'];
			$type = (string)$share['node_type'];
			if ($type === self::NODE_TYPE_TABLE) {
				$tableIds[$nodeId] = true;
			} elseif ($type === self::NODE_TYPE_VIEW) {
				$viewIds[$nodeId] = true;
			} elseif ($type === self::NODE_TYPE_CONTEXT) {
				$contextIds[$nodeId] = true;
			}
		}
		return [
			self::NODE_TYPE_TABLE => array_keys($tableIds),
			self::NODE_TYPE_VIEW => array_keys($viewIds),
			self::NODE_TYPE_CONTEXT => array_keys($contextIds),
		];
	}

	/**
	 * @param array<string, mixed> $share
	 * @param array<int, string> $tableNames
	 * @param array<int, string> $viewNames
	 * @param array<int, string> $contextNames
	 */
	private function resolveObjectName(array $share, array $tableNames, array $viewNames, array $contextNames): string {
		$nodeId = (int)$share['node_id'];
		$nodeType = (string)$share['node_type'];
		if ($nodeType === self::NODE_TYPE_TABLE) {
			return $this->l10n->t('%s (Table)', [$tableNames[$nodeId] ?? $this->l10n->t('Table %s', [$nodeId])]);
		}
		if ($nodeType === self::NODE_TYPE_VIEW) {
			return $this->l10n->t('%s (View)', [$viewNames[$nodeId] ?? $this->l10n->t('View %s', [$nodeId])]);
		}
		if ($nodeType === self::NODE_TYPE_CONTEXT) {
			return $this->l10n->t('%s (Application)', [$contextNames[$nodeId] ?? $this->l10n->t('Application %s', [$nodeId])]);
		}
		$this->logger->warning(
			'Tables ShareReview: unknown node type {type} for share node {id}',
			['type' => $nodeType, 'id' => $nodeId]
		);
		return $this->l10n->t('Unknown %s', [$nodeId]);
	}

	private function mapReceiverType(string $receiverType): int {
		if ($receiverType === 'user') {
			return IShare::TYPE_USER;
		}
		if ($receiverType === 'group') {
			return IShare::TYPE_GROUP;
		}
		if ($receiverType === 'link') {
			return IShare::TYPE_LINK;
		}
		if ($receiverType === 'circle') {
			return IShare::TYPE_CIRCLE;
		}
		$this->logger->warning(
			'Tables ShareReview: unknown receiver type {type}, falling back to user share type',
			['type' => $receiverType]
		);
		return IShare::TYPE_USER;
	}

	/** @param array<string, mixed> $share */
	private function resolveShareTime(array $share): string {
		$createdAt = (string)($share['created_at'] ?? '1970-01-01 01:00:00');
		$lastEditAt = isset($share['last_edit_at']) ? (string)$share['last_edit_at'] : null;
		if ($lastEditAt !== null && $lastEditAt > $createdAt) {
			return $lastEditAt;
		}
		return $createdAt;
	}

	/** @param array<string, mixed> $share */
	private function computePermissions(array $share): int {
		$permissions = 0;
		if ($share['permission_read']) {
			$permissions |= Constants::PERMISSION_READ;
		}
		if ($share['permission_update']) {
			$permissions |= Constants::PERMISSION_UPDATE;
		}
		if ($share['permission_create']) {
			$permissions |= Constants::PERMISSION_CREATE;
		}
		if ($share['permission_delete']) {
			$permissions |= Constants::PERMISSION_DELETE;
		}
		if ($share['permission_manage']) {
			$permissions |= Constants::PERMISSION_SHARE;
		}
		return $permissions > 0 ? $permissions : Constants::PERMISSION_READ;
	}
}
