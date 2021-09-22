<?php

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Row extends Entity implements JsonSerializable {
	protected $tableId;
    protected $createdBy;
    protected $createdAt;
    protected $lastEditBy;
    protected $lastEditAt;
    protected $data;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('tableId', 'integer');
    }

	public function jsonSerialize(): array {
		return [
			'id'            => $this->id,
            'tableId'       => $this->tableId,
			'createdBy'     => $this->createdBy,
            'createdAt'     => $this->createdAt,
            'lastEditBy'    => $this->lastEditBy,
            'lastEditAt'    => $this->lastEditAt,
            'data'          => $this->data,
		];
	}
}
