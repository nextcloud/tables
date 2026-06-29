<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

class ShareCreate {
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
		private readonly ?ShareToken $shareToken = null,
	) {
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
}
