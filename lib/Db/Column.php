<?php

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Column extends Entity implements JsonSerializable {
	protected $title;
	protected $tableId;
	protected $createdBy;
	protected $createdAt;
	protected $lastEditBy;
	protected $lastEditAt;
	protected $type;
	protected $subtype;
	protected $mandatory;
	protected $description;
	protected $orderWeight;

	// type number
	protected $numberDefault;
	protected $numberMin;
	protected $numberMax;
	protected $numberDecimals;
	protected $numberPrefix;
	protected $numberSuffix;

	// type text
	protected $textDefault;
	protected $textAllowedPattern;
	protected $textMaxLength;

	// type selection
	protected $selectionOptions;
	protected $selectionDefault;

	// type datetime
	protected $datetimeDefault;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('tableId', 'integer');
		$this->addType('mandatory', 'boolean');
		$this->addType('orderWeight', 'integer');

		// type number
		$this->addType('numberDecimals', 'integer');
		$this->addType('numberMin', 'float');
		$this->addType('numberMax', 'float');
		$this->addType('numberDefault', 'float');

		// type text
		$this->addType('textMaxLength', 'integer');
	}

	/** @noinspection PhpUndefinedMethodInspection */
	public function getSelectionOptionsArray() {
		return \json_decode($this->getSelectionOptions(), true);
	}

	/** @noinspection PhpUndefinedMethodInspection */
	public function setSelectionOptionsArray($array) {
		$json = \json_encode($array);
		$this->setSelectionOptions($json);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'tableId' => $this->tableId,
			'title' => $this->title,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'lastEditBy' => $this->lastEditBy,
			'lastEditAt' => $this->lastEditAt,
			'type' => $this->type,
			'subtype' => $this->subtype,
			'mandatory' => $this->mandatory,
			'description' => $this->description,
			'orderWeight' => $this->orderWeight,

			// type number
			'numberDefault' => $this->numberDefault,
			'numberMin' => $this->numberMin,
			'numberMax' => $this->numberMax,
			'numberDecimals' => $this->numberDecimals,
			'numberPrefix' => $this->numberPrefix,
			'numberSuffix' => $this->numberSuffix,

			// type text
			'textDefault' => $this->textDefault,
			'textAllowedPattern' => $this->textAllowedPattern,
			'textMaxLength' => $this->textMaxLength,

			// type selection
			'selectionOptions' => $this->getSelectionOptionsArray(),
			'selectionDefault' => $this->selectionDefault,

			// type datetime
			'datetimeDefault' => $this->datetimeDefault,
		];
	}
}
