<?php

declare(strict_types=1);

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getRelationColumnId()
 * @method void setRelationColumnId(int $relationColumnId)
 * @method int getSourceRowId()
 * @method void setSourceRowId(int $sourceRowId)
 * @method int getTargetRowId()
 * @method void setTargetRowId(int $targetRowId)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $createdBy)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class RowRelation extends Entity implements \JsonSerializable {
	protected ?int $relationColumnId = null;
	protected ?int $sourceRowId = null;
	protected ?int $targetRowId = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;

	public function __construct() {
		$this->addType('relationColumnId', 'integer');
		$this->addType('sourceRowId', 'integer');
		$this->addType('targetRowId', 'integer');
		$this->addType('createdBy', 'string');
		$this->addType('createdAt', 'string');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'relationColumnId' => $this->getRelationColumnId(),
			'sourceRowId' => $this->getSourceRowId(),
			'targetRowId' => $this->getTargetRowId(),
			'createdBy' => $this->getCreatedBy(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
