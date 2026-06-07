<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\ShareReview;

use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Share\IShare;
use OCP\Share\ShareReview\Events\ShareReviewAccessCheckEvent;
use OCP\Share\ShareReview\IShareReviewSource;
use OCP\Share\ShareReview\ShareReviewEntry;
use OCP\Share\ShareReview\ShareReviewPermission;
use Psr\Log\LoggerInterface;

class ShareReviewSource implements IShareReviewSource {

	private const NODE_TYPE_TABLE = 'table';
	private const NODE_TYPE_VIEW = 'view';
	private const NODE_TYPE_CONTEXT = 'context';

	private const RECEIVER_TYPE_LINK = 'link';

	public const PERMISSION_READ = 'tables:read';
	public const PERMISSION_UPDATE = 'tables:update';
	public const PERMISSION_CREATE = 'tables:create';
	public const PERMISSION_DELETE = 'tables:delete';
	public const PERMISSION_MANAGE = 'tables:manage';

	/** @var array<string, ShareReviewPermission>|null */
	private ?array $permissionCatalog = null;

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
	 * @return list<ShareReviewEntry>
	 */
	public function getShares(): array {
		try {
			$nodeIdsByType = $this->shareMapper->findSharedNodeIdsByType();
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch shared node IDs: {message}', ['message' => $e->getMessage()]);
			return [];
		}

		try {
			$tableNames = $this->tableMapper->findIdToTitleMap($nodeIdsByType[self::NODE_TYPE_TABLE] ?? []);
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch table names: {message}', ['message' => $e->getMessage()]);
			$tableNames = [];
		}

		try {
			$viewNames = $this->viewMapper->findIdToTitleMap($nodeIdsByType[self::NODE_TYPE_VIEW] ?? []);
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch view names: {message}', ['message' => $e->getMessage()]);
			$viewNames = [];
		}

		try {
			$contextNames = $this->contextMapper->findIdToNameMap($nodeIdsByType[self::NODE_TYPE_CONTEXT] ?? []);
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch application names: {message}', ['message' => $e->getMessage()]);
			$contextNames = [];
		}

		$formatted = [];
		try {
			foreach ($this->shareMapper->findAllRaw() as $share) {
				$formatted[] = $this->buildEntry($share, $tableNames, $viewNames, $contextNames);
			}
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch shares: {message}', ['message' => $e->getMessage()]);
			return [];
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
	private function buildEntry(array $share, array $tableNames, array $viewNames, array $contextNames): ShareReviewEntry {
		return new ShareReviewEntry(
			id: (string)$share['id'],
			object: $this->resolveObjectName($share, $tableNames, $viewNames, $contextNames),
			initiator: (string)$share['sender'],
			type: $this->mapReceiverType((string)$share['receiver_type']),
			recipient: $share['receiver_type'] === self::RECEIVER_TYPE_LINK
				? (string)$share['token']
				: (string)$share['receiver'],
			lastModifiedTimestamp: strtotime($this->resolveShareTime($share)) ?: 0,
			permissions: $this->buildPermissions($share),
			hasPassword: $share['password'] !== null,
		);
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

	/**
	 * @param array<string, mixed> $share
	 * @return list<ShareReviewPermission>
	 */
	private function buildPermissions(array $share): array {
		$catalog = $this->permissionCatalog();
		$permissions = [];
		if ($share['permission_read']) {
			$permissions[] = $catalog[self::PERMISSION_READ];
		}
		if ($share['permission_update']) {
			$permissions[] = $catalog[self::PERMISSION_UPDATE];
		}
		if ($share['permission_create']) {
			$permissions[] = $catalog[self::PERMISSION_CREATE];
		}
		if ($share['permission_delete']) {
			$permissions[] = $catalog[self::PERMISSION_DELETE];
		}
		if ($permissions === []) {
			// A share without any read/write flags still grants access to the shared node
			$permissions[] = $catalog[self::PERMISSION_READ];
		}
		if ($share['permission_manage']) {
			$permissions[] = $catalog[self::PERMISSION_MANAGE];
		}
		return $permissions;
	}

	/**
	 * The permission objects are immutable and identical for every share row,
	 * so they are built once per request instead of once per row.
	 *
	 * All permission IDs are namespaced to this app, and labels and hints are
	 * translated from this app's own catalog — the app owning a permission
	 * also owns its wording in every language.
	 *
	 * @return array<string, ShareReviewPermission>
	 */
	private function permissionCatalog(): array {
		return $this->permissionCatalog ??= [
			self::PERMISSION_READ => new ShareReviewPermission(self::PERMISSION_READ, $this->l10n->t('Read'), priority: 80),
			self::PERMISSION_UPDATE => new ShareReviewPermission(self::PERMISSION_UPDATE, $this->l10n->t('Update'), priority: 70),
			self::PERMISSION_CREATE => new ShareReviewPermission(self::PERMISSION_CREATE, $this->l10n->t('Create'), priority: 60),
			self::PERMISSION_DELETE => new ShareReviewPermission(self::PERMISSION_DELETE, $this->l10n->t('Delete'), priority: 50),
			self::PERMISSION_MANAGE => new ShareReviewPermission(self::PERMISSION_MANAGE, $this->l10n->t('Manage'), $this->l10n->t('Administer the shared table and its sharing'), 30),
		];
	}
}
