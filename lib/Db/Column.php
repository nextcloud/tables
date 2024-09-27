<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;

use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @psalm-import-type TablesColumn from ResponseDefinitions
 *
 * @method string getTitle()
 * @method setTitle(string $title)
 * @method int getTableId()
 * @method setTableId(int $tableId)
 * @method string getCreatedBy()
 * @method setCreatedBy(string $createdBy)
 * @method string getCreatedByDisplayName()
 * @method setCreatedByDisplayName(string $displayName)
 * @method string getCreatedAt()
 * @method setCreatedAt(string $createdAt)
 * @method string getLastEditBy()
 * @method setLastEditBy(string $lastEditBy)
 * @method string getLastEditByDisplayName()
 * @method setLastEditByDisplayName(string $displayName)
 * @method string getLastEditAt()
 * @method setLastEditAt(string $lastEditAt)
 * @method string getType()
 * @method setType(string $type)
 * @method string getSubtype()
 * @method setSubtype(string $subtype)
 * @method bool getMandatory()
 * @method setMandatory(?bool $mandatory)
 * @method string getDescription()
 * @method setDescription(?string $description)
 * @method float getNumberDefault()
 * @method setNumberDefault(?float $numberDefault)
 * @method float getNumberMin()
 * @method setNumberMin(?float $numberMin)
 * @method float getNumberMax()
 * @method setNumberMax(?float $numberMax)
 * @method int getNumberDecimals()
 * @method setNumberDecimals(?int $numberDecimals)
 * @method string getNumberPrefix()
 * @method setNumberPrefix(?string $numberPrefix)
 * @method string getNumberSuffix()
 * @method setNumberSuffix(?string $numberSuffix)
 * @method string getTextDefault()
 * @method setTextDefault(?string $textDefault)
 * @method string getTextAllowedPattern()
 * @method setTextAllowedPattern(?string $textAllowedPattern)
 * @method int getTextMaxLength()
 * @method setTextMaxLength(?int $textMaxLength)
 * @method string getSelectionOptions()
 * @method setSelectionOptions(?string $selectionOptionsArray)
 * @method string getSelectionDefault()
 * @method setSelectionDefault(?string $selectionDefault)
 * @method string getDatetimeDefault()
 * @method setDatetimeDefault(?string $datetimeDefault)
 * @method string getUsergroupDefault()
 * @method setUsergroupDefault(?string $usergroupDefaultArray)
 * @method bool getUsergroupMultipleItems()
 * @method setUsergroupMultipleItems(?bool $usergroupMultipleItems)
 * @method bool getUsergroupSelectUsers()
 * @method setUsergroupSelectUsers(?bool $usergroupSelectUsers)
 * @method bool getUsergroupSelectGroups()
 * @method setUsergroupSelectGroups(?bool $usergroupSelectGroups)
 * @method bool getShowUserStatus()
 * @method setShowUserStatus(?bool $showUserStatus)
 */
class Column extends Entity implements JsonSerializable {
	// Meta column types
	public const TYPE_META_ID = -1;
	public const TYPE_META_CREATED_BY = -2;
	public const TYPE_META_UPDATED_BY = -3;
	public const TYPE_META_CREATED_AT = -4;
	public const TYPE_META_UPDATED_AT = -5;

	public const TYPE_SELECTION = 'selection';
	public const TYPE_TEXT = 'text';
	public const TYPE_NUMBER = 'number';
	public const TYPE_DATETIME = 'datetime';
	public const TYPE_USERGROUP = 'usergroup';

	protected string $title = '';
	protected int $tableId = 0;
	protected string $createdBy = '';
	protected string $createdByDisplayName = '';
	protected string $createdAt = '';
	protected string $lastEditBy = '';
	protected string $lastEditByDisplayName = '';
	protected string $lastEditAt = '';
	protected string $type = '';
	protected string $subtype = '';
	protected bool $mandatory = false;
	protected string $description = '';
	protected ?int $orderWeight = null; // Deprecated

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

	// type usergroup
	protected ?string $usergroupDefault = null;
	protected ?bool $usergroupMultipleItems = null;
	protected ?bool $usergroupSelectUsers = null;
	protected ?bool $usergroupSelectGroups = null;
	protected ?bool $showUserStatus = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('tableId', 'integer');
		$this->addType('mandatory', 'boolean');

		// type number
		$this->addType('numberDecimals', 'integer');
		$this->addType('numberMin', 'float');
		$this->addType('numberMax', 'float');
		$this->addType('numberDefault', 'float');

		// type text
		$this->addType('textMaxLength', 'integer');

		// // type usergroup
		$this->addType('usergroupMultipleItems', 'boolean');
		$this->addType('usergroupSelectUsers', 'boolean');
		$this->addType('usergroupSelectGroups', 'boolean');
		$this->addType('showUserStatus', 'boolean');
	}

	public static function fromDto(ColumnDto $data): self {
		$column = new self();
		$column->setTitle($data->getTitle());
		$column->setType($data->getType());
		$column->setSubtype($data->getSubtype() ?? '');
		$column->setMandatory($data->isMandatory() ?? false);
		$column->setDescription($data->getDescription() ?? '');
		$column->setTextDefault($data->getTextDefault());
		$column->setTextAllowedPattern($data->getTextAllowedPattern());
		$column->setTextMaxLength($data->getTextMaxLength());
		$column->setNumberDefault($data->getNumberDefault());
		$column->setNumberMin($data->getNumberMin());
		$column->setNumberMax($data->getNumberMax());
		$column->setNumberDecimals($data->getNumberDecimals());
		$column->setNumberPrefix($data->getNumberPrefix() ?? '');
		$column->setNumberSuffix($data->getNumberSuffix() ?? '');
		$column->setSelectionOptions($data->getSelectionOptions());
		$column->setSelectionDefault($data->getSelectionDefault());
		$column->setDatetimeDefault($data->getDatetimeDefault());
		$column->setUsergroupDefault($data->getUsergroupDefault());
		$column->setUsergroupMultipleItems($data->getUsergroupMultipleItems());
		$column->setUsergroupSelectUsers($data->getUsergroupSelectUsers());
		$column->setUsergroupSelectGroups($data->getUsergroupSelectGroups());
		$column->setShowUserStatus($data->getShowUserStatus());
		return $column;
	}

	/**
	 * @return array{id: int, label: string}|list<array{id: int, label: string}>
	 */
	public function getUsergroupDefaultArray():array {
		$default = $this->getUsergroupDefault();
		if ($default !== "" && $default !== null) {
			return \json_decode($default, true) ?? [];
		} else {
			return [];
		}
	}

	public function setUsergroupDefaultArray(array $array):void {
		$json = \json_encode($array);
		$this->setUsergroup($json);
	}

	/**
	 * @return list<array{id: int, label: string}>
	 */
	public function getSelectionOptionsArray():array {
		$options = $this->getSelectionOptions();
		if ($options !== "" && $options !== null && $options !== 'null') {
			return \json_decode($options, true);
		} else {
			return [];
		}
	}

	public function setSelectionOptionsArray(array $array):void {
		$json = \json_encode($array);
		$this->setSelectionOptions($json);
	}

	/**
	 * @psalm-return TablesColumn
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'tableId' => $this->tableId,
			'title' => $this->title,
			'createdBy' => $this->createdBy,
			'createdByDisplayName' => $this->createdByDisplayName,
			'createdAt' => $this->createdAt,
			'lastEditBy' => $this->lastEditBy,
			'lastEditByDisplayName' => $this->lastEditByDisplayName,
			'lastEditAt' => $this->lastEditAt,
			'type' => $this->type,
			'subtype' => $this->subtype,
			'mandatory' => $this->mandatory,
			'description' => $this->description,

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

			// type usergroup
			'usergroupDefault' => $this->getUsergroupDefaultArray(),
			'usergroupMultipleItems' => $this->usergroupMultipleItems,
			'usergroupSelectUsers' => $this->usergroupSelectUsers,
			'usergroupSelectGroups' => $this->usergroupSelectGroups,
			'showUserStatus' => $this->showUserStatus,
		];
	}
}
