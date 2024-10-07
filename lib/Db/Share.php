<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;

use OCA\Tables\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-import-type TablesShare from ResponseDefinitions
 *
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @method string getSender()
 * @method setSender(string $sender)
 * @method string getReceiver()
 * @method setReceiver(string $receiver)
 * @method string getReceiverDisplayName()
 * @method setReceiverDisplayName(string $receiverDisplayName)
 * @method string getReceiverType()
 * @method setReceiverType(string $receiverType)
 * @method int getNodeId()
 * @method setNodeId(int $nodeId)
 * @method string getNodeType()
 * @method setNodeType(string $nodeType)
 * @method bool getPermissionRead()
 * @method setPermissionRead(bool $permissionRead)
 * @method bool getPermissionCreate()
 * @method setPermissionCreate(bool $permissionCreate)
 * @method bool getPermissionUpdate()
 * @method setPermissionUpdate(bool $permissionUpdate)
 * @method bool getPermissionDelete()
 * @method setPermissionDelete(bool $permissionDelete)
 * @method bool getPermissionManage()
 * @method setPermissionManage(bool $permissionManage)
 * @method string getCreatedAt()
 * @method setCreatedAt(string $createdAt)
 * @method string getLastEditAt()
 * @method setLastEditAt(string $lastEditAt)
 */
class Share extends Entity implements JsonSerializable {
	protected string $sender = ''; // is also owner

	protected string $receiver = '';
	protected string $receiverDisplayName = '';
	protected string $receiverType = ''; // user, group
	protected int $nodeId = 0;
	protected string $nodeType = '';
	protected bool $permissionRead = false;
	protected bool $permissionCreate = false;
	protected bool $permissionUpdate = false;
	protected bool $permissionDelete = false;
	protected bool $permissionManage = false;
	protected string $createdAt = '';
	protected string $lastEditAt = '';

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
