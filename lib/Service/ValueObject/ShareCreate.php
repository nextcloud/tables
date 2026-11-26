<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

use InvalidArgumentException;
use OCA\Tables\Constants\ShareReceiverType;

class ShareCreate {
	/**
	 * Receiver types a share can be resolved for, per node type. Contexts are
	 * only matched by user and by group in
	 * ContextMapper::applyOwnedOrSharedQuery(), so a share to any other
	 * receiver would be stored without ever granting access.
	 */
	private const RECEIVER_TYPES_PER_NODE_TYPE = [
		'context' => [
			ShareReceiverType::USER,
			ShareReceiverType::GROUP,
		],
	];

	private const DEFAULT_RECEIVER_TYPES = [
		ShareReceiverType::USER,
		ShareReceiverType::GROUP,
		ShareReceiverType::CIRCLE,
		ShareReceiverType::LINK,
		ShareReceiverType::REMOTE,
	];

	public function __construct(
		private readonly int $nodeId,
		private readonly string $nodeType,
		private readonly string $receiver,
		private readonly string $receiverType,
		private readonly bool $permissionRead,
		private readonly bool $permissionCreate,
		private readonly bool $permissionUpdate,
		private readonly bool $permissionDelete,
		private readonly bool $permissionManage,
		private readonly int $displayMode,
		private readonly ?string $password = null,
		private ?ShareToken $shareToken = null,
	) {
		$supportedReceiverTypes = self::RECEIVER_TYPES_PER_NODE_TYPE[$nodeType] ?? self::DEFAULT_RECEIVER_TYPES;
		if (!in_array($receiverType, $supportedReceiverTypes, true)) {
			throw new InvalidArgumentException(sprintf(
				'A %s cannot be shared with a receiver of type "%s", expected one of: %s',
				$nodeType,
				$receiverType,
				implode(', ', $supportedReceiverTypes),
			));
		}
	}

	public function getNodeId(): int {
		return $this->nodeId;
	}

	public function getNodeType(): string {
		return $this->nodeType;
	}

	public function getReceiver(): string {
		return $this->receiver;
	}

	public function getReceiverType(): string {
		return $this->receiverType;
	}

	public function getPermissionRead(): bool {
		return $this->permissionRead;
	}

	public function getPermissionCreate(): bool {
		return $this->permissionCreate;
	}

	public function getPermissionUpdate(): bool {
		return $this->permissionUpdate;
	}

	public function getPermissionDelete(): bool {
		return $this->permissionDelete;
	}

	public function getPermissionManage(): bool {
		return $this->permissionManage;
	}

	public function getDisplayMode(): int {
		return $this->displayMode;
	}

	public function getPassword(): ?string {
		return $this->password;
	}

	public function getShareToken(): ?ShareToken {
		return $this->shareToken;
	}

	public function setShareToken(ShareToken $token): void {
		$this->shareToken = $token;
	}
}
