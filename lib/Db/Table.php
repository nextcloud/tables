<?php

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Table extends Entity implements JsonSerializable {
	protected $title;
	protected $ownership;
	protected $createdBy;
    protected $createdAt;
    protected $lastEditBy;
    protected $lastEditAt;
    protected $isShared;
    protected $onSharePermissions;

    public function __construct() {
        $this->addType('id', 'integer');
    }

	public function jsonSerialize(): array {
		return [
			'id'            => $this->id,
            'title'         => $this->title,
			'ownership'     => $this->ownership,
			'createdBy'     => $this->createdBy,
            'createdAt'     => $this->createdAt,
            'lastEditBy'    => $this->lastEditBy,
            'lastEditAt'    => $this->lastEditAt,
            'isShared'      => !!$this->isShared,
            'onSharePermissions' => $this->onSharePermissions,
		];
	}
}
