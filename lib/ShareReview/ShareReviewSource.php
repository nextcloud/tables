<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\ShareReview;

use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Service\ShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Share\IShare;
use OCP\Share\ShareReview\Events\ShareReviewAccessCheckEvent;
use OCP\Share\ShareReview\IPaginatedShareReviewSource;
use OCP\Share\ShareReview\ShareReviewCounts;
use OCP\Share\ShareReview\ShareReviewEntry;
use OCP\Share\ShareReview\ShareReviewPage;
use OCP\Share\ShareReview\ShareReviewPermission;
use OCP\Share\ShareReview\ShareReviewQuery;
use Psr\Log\LoggerInterface;

/**
 * Tables' table, view and application shares as share-review shares, with the
 * paginated query contract evaluated in SQL on the share table joined with
 * the three node tables.
 */
class ShareReviewSource implements IPaginatedShareReviewSource {

	private const NODE_TYPE_TABLE = 'table';
	private const NODE_TYPE_VIEW = 'view';
	private const NODE_TYPE_CONTEXT = 'context';

	private const RECEIVER_TYPE_LINK = 'link';

	public const PERMISSION_READ = 'tables:read';
	public const PERMISSION_UPDATE = 'tables:update';
	public const PERMISSION_CREATE = 'tables:create';
	public const PERMISSION_DELETE = 'tables:delete';
	public const PERMISSION_MANAGE = 'tables:manage';

	/** Native receiver type to IShare type. */
	private const RECEIVER_TYPES = [
		'user' => IShare::TYPE_USER,
		'group' => IShare::TYPE_GROUP,
		self::RECEIVER_TYPE_LINK => IShare::TYPE_LINK,
		'circle' => IShare::TYPE_CIRCLE,
		'remote' => IShare::TYPE_REMOTE,
	];

	/** Opaque share-review permission id to the share column that grants it. */
	private const PERMISSION_COLUMNS = [
		self::PERMISSION_READ => 'permission_read',
		self::PERMISSION_UPDATE => 'permission_update',
		self::PERMISSION_CREATE => 'permission_create',
		self::PERMISSION_DELETE => 'permission_delete',
		self::PERMISSION_MANAGE => 'permission_manage',
	];

	/** @var array<string, ShareReviewPermission>|null */
	private ?array $permissionCatalog = null;

	public function __construct(
		private readonly ShareMapper $shareMapper,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
		private readonly ShareService $shareService,
		private readonly IEventDispatcher $eventDispatcher,
	) {
	}

	public function getName(): string {
		// Stable id: keys the review state and the access-check event, so it
		// must not depend on the locale — the translated label is getDisplayName()
		return 'Tables';
	}

	public function getDisplayName(): string {
		return $this->l10n->t('Tables');
	}

	/**
	 * All shares, read page by page in a stable order.
	 *
	 * @return list<ShareReviewEntry>
	 */
	public function getShares(): array {
		// enumerated on the immutable id order, so concurrent edits (which
		// move last_edit_at) can neither duplicate nor skip rows
		$entries = [];
		try {
			foreach ($this->shareMapper->findAllForShareReview() as $row) {
				$entries[] = $this->buildEntry($row);
			}
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch shares: {message}', ['message' => $e->getMessage()]);
			return [];
		}
		return $entries;
	}

	public function queryShares(ShareReviewQuery $query): ShareReviewPage {
		try {
			$rows = $this->shareMapper->findPageForShareReview($query, $this->receiverTypes($query), $this->permissionColumns($query));
			$counts = $this->shareMapper->countForShareReview($query, $this->receiverTypes($query), $this->permissionColumns($query));
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch shares: {message}', ['message' => $e->getMessage()]);
			return new ShareReviewPage([], new ShareReviewCounts(0, 0));
		}
		return new ShareReviewPage(array_map($this->buildEntry(...), $rows), $counts);
	}

	public function countShares(ShareReviewQuery $query): ShareReviewCounts {
		try {
			return $this->shareMapper->countForShareReview($query, $this->receiverTypes($query), $this->permissionColumns($query));
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to count shares: {message}', ['message' => $e->getMessage()]);
			return new ShareReviewCounts(0, 0);
		}
	}

	public function countSharesByType(ShareReviewQuery $query): array {
		try {
			$nativeCounts = $this->shareMapper->countByTypeForShareReview($query, $this->receiverTypes($query), $this->permissionColumns($query));
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to count shares by type: {message}', ['message' => $e->getMessage()]);
			return [];
		}
		$counts = [];
		foreach ($nativeCounts as $receiverType => $count) {
			// unknown native types are excluded here as they are from the
			// shareTypes filter, so count and filtered list always agree
			if (!isset(self::RECEIVER_TYPES[$receiverType])) {
				continue;
			}
			$type = self::RECEIVER_TYPES[$receiverType];
			$counts[$type] = ($counts[$type] ?? 0) + $count;
		}
		return $counts;
	}

	public function getShare(string $shareId): ?ShareReviewEntry {
		if (!ctype_digit($shareId)) {
			return null;
		}
		try {
			$share = $this->shareMapper->findForShareReview((int)$shareId);
		} catch (Exception $e) {
			$this->logger->error('Tables ShareReview: failed to fetch share {id}: {message}', ['id' => $shareId, 'message' => $e->getMessage()]);
			return null;
		}
		return $share === null ? null : $this->buildEntry($share);
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
	 * The native receiver types a shareTypes filter selects; a requested type
	 * tables never produces matches nothing.
	 *
	 * @return list<string>|null null = no type filter
	 */
	private function receiverTypes(ShareReviewQuery $query): ?array {
		if ($query->shareTypes === null) {
			return null;
		}
		return array_keys(array_intersect(self::RECEIVER_TYPES, $query->shareTypes));
	}

	/**
	 * The permission columns an opaque permission-id filter selects; ids of
	 * other apps match nothing.
	 *
	 * @return list<string>|null null = no permission filter
	 */
	private function permissionColumns(ShareReviewQuery $query): ?array {
		if ($query->permissionIds === null) {
			return null;
		}
		return array_values(array_intersect_key(self::PERMISSION_COLUMNS, array_fill_keys($query->permissionIds, true)));
	}

	/**
	 * @param array<string, mixed> $share
	 */
	private function buildEntry(array $share): ShareReviewEntry {
		return new ShareReviewEntry(
			id: (string)$share['id'],
			object: $this->resolveObjectName($share),
			initiator: (string)$share['sender'],
			type: $this->mapReceiverType((string)$share['receiver_type']),
			recipient: $share['receiver_type'] === self::RECEIVER_TYPE_LINK
				? (string)$share['token']
				: (string)$share['receiver'],
			// last_edit_at is set on insert and bumped on every update; created_at
			// only covers rows a migration left without it
			lastModifiedTimestamp: strtotime((string)($share['last_edit_at'] ?? $share['created_at'] ?? '')) ?: 0,
			permissions: $this->buildPermissions($share),
			hasPassword: $share['password'] !== null,
		);
	}

	/**
	 * @param array<string, mixed> $share
	 */
	private function resolveObjectName(array $share): string {
		$nodeId = (int)$share['node_id'];
		$nodeType = (string)$share['node_type'];
		$name = isset($share['node_name']) ? (string)$share['node_name'] : null;
		if ($nodeType === self::NODE_TYPE_TABLE) {
			return $this->l10n->t('%s (Table)', [$name ?? $this->l10n->t('Table %s', [$nodeId])]);
		}
		if ($nodeType === self::NODE_TYPE_VIEW) {
			return $this->l10n->t('%s (View)', [$name ?? $this->l10n->t('View %s', [$nodeId])]);
		}
		if ($nodeType === self::NODE_TYPE_CONTEXT) {
			return $this->l10n->t('%s (Application)', [$name ?? $this->l10n->t('Application %s', [$nodeId])]);
		}
		$this->logger->warning(
			'Tables ShareReview: unknown node type {type} for share node {id}',
			['type' => $nodeType, 'id' => $nodeId]
		);
		return $this->l10n->t('Unknown %s', [$nodeId]);
	}

	private function mapReceiverType(string $receiverType): int {
		if (isset(self::RECEIVER_TYPES[$receiverType])) {
			return self::RECEIVER_TYPES[$receiverType];
		}
		$this->logger->warning(
			'Tables ShareReview: unknown receiver type {type}, falling back to user share type',
			['type' => $receiverType]
		);
		return IShare::TYPE_USER;
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
