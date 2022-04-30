<?php

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Share extends Entity implements JsonSerializable {
	protected $userSender;
    protected $userReceiver;
    protected $nodeId;
    protected $nodeType;
    protected $permissionRead;
    protected $permissionCreate;
    protected $permissionUpdate;
    protected $permissionDelete;
    protected $permissionManage;
    protected $createdAt;
    protected $lastEditAt;

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

	public function jsonSerialize(): array {
		return [
			'id'                => $this->id,
            'nodeId'            => $this->nodeId,
            'nodeType'          => $this->nodeType,
            'permissionRead'    => $this->permissionRead,
            'permissionCreate'  => $this->permissionCreate,
            'permissionUpdate'  => $this->permissionUpdate,
            'permissionDelete'  => $this->permissionDelete,
            'permissionManage'  => $this->permissionManage,
        ];
	}
}
