<?php

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Column extends Entity implements JsonSerializable {
	protected ?string $title = null;
	protected ?int $tableId = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;
	protected ?string $type = null;
	protected ?string $subtype = null;
	protected ?bool $mandatory = null;
	protected ?string $description = null;
	protected ?int $orderWeight = null;

	// type number
	protected ?float $numberDefault = null;
	protected ?float $numberMin = null;
	protected ?float $numberMax = null;
	protected ?int $numberDecimals = null;
	protected ?string $numberPrefix = null;
	protected ?string $numberSuffix = null;

	// type text
	protected ?string $textDefault = null;
	protected ?string $textAllowedPattern = null;
	protected ?int $textMaxLength = null;

	// type selection
	protected ?string $selectionOptions = null;
	protected ?string $selectionDefault = null;

	// type datetime
	protected ?string $datetimeDefault = null;

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
	public function getSelectionOptionsArray():array {
		$options = $this->getSelectionOptions();
		if ($options !== "" && $options !== null) {
			return \json_decode($options, true);
		} else {
			return [];
		}
	}

	/** @noinspection PhpUndefinedMethodInspection */
	public function setSelectionOptionsArray(array $array):void {
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
