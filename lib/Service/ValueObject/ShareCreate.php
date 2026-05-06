<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

class ShareCreate {
	public function __construct(
		private int $nodeId,
		private string $nodeType,
		private string $receiver,
		private string $receiverType,
		private bool $permissionRead,
		private bool $permissionCreate,
		private bool $permissionUpdate,
		private bool $permissionDelete,
		private bool $permissionManage,
		private int $displayMode,
		private ?string $password = null,
		private ?ShareToken $shareToken = null,
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
