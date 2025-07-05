<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;

use OCA\Tables\ResponseDefinitions;

/**
 * @psalm-import-type TablesShare from ResponseDefinitions
 *
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @method getSender(): string
 * @method setSender(string $sender)
 * @method getReceiver(): string
 * @method setReceiver(string $receiver)
 * @method getReceiverDisplayName(): string
 * @method setReceiverDisplayName(string $receiverDisplayName)
 * @method getReceiverType(): string
 * @method setReceiverType(string $receiverType)
 * @method getNodeId(): int
 * @method setNodeId(int $nodeId)
 * @method getNodeType(): string
 * @method setNodeType(string $nodeType)
 * @method getPermissionRead(): bool
 * @method setPermissionRead(bool $permissionRead)
 * @method getPermissionCreate(): bool
 * @method setPermissionCreate(bool $permissionCreate)
 * @method getPermissionUpdate(): bool
 * @method setPermissionUpdate(bool $permissionUpdate)
 * @method getPermissionDelete(): bool
 * @method setPermissionDelete(bool $permissionDelete)
 * @method getPermissionManage(): bool
 * @method setPermissionManage(bool $permissionManage)
 * @method getCreatedAt(): string
 * @method setCreatedAt(string $createdAt)
 * @method getLastEditAt(): string
 * @method setLastEditAt(string $lastEditAt)
 */
class Share extends EntitySuper implements JsonSerializable {
	protected ?string $sender = null; // is also owner

	protected ?string $receiver = null;
	protected ?string $receiverType = null; // user, group, circle
	protected ?int $nodeId = null;
	protected ?string $nodeType = null;
	protected ?bool $permissionRead = null;
	protected ?bool $permissionCreate = null;
	protected ?bool $permissionUpdate = null;
	protected ?bool $permissionDelete = null;
	protected ?bool $permissionManage = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditAt = null;

	// virtual properties
	protected ?string $receiverDisplayName = null;

	protected const VIRTUAL_PROPERTIES = ['receiverDisplayName'];

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('nodeId', 'integer');

		// type bool
		$this->addType('permissionRead', 'boolean');
		$this->addType('permissionCreate', 'boolean');
		$this->addType('permissionUpdate', 'boolean');
		$this->addType('permissionDelete', 'boolean');
		$this->addType('permissionManage', 'boolean');
	}

	/**
	 * @psalm-return TablesShare
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'nodeId' => $this->nodeId,
			'nodeType' => $this->nodeType,
			'permissionRead' => $this->permissionRead,
			'permissionCreate' => $this->permissionCreate,
			'permissionUpdate' => $this->permissionUpdate,
			'permissionDelete' => $this->permissionDelete,
			'permissionManage' => $this->permissionManage,
			'sender' => $this->sender,
			'receiver' => $this->receiver,
			'receiverDisplayName' => $this->receiverDisplayName,
			'receiverType' => $this->receiverType,
			'createdAt' => $this->createdAt,
			'lastEditAt' => $this->lastEditAt,
		];
	}
}
