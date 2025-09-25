<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;

use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ValueObject\ViewColumnInformation;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @psalm-import-type TablesColumn from ResponseDefinitions
 *
 * @method getTitle(): string
 * @method setTitle(string $title)
 * @method getTableId(): int
 * @method setTableId(int $tableId)
 * @method getCreatedBy(): string
 * @method setCreatedBy(string $createdBy)
 * @method getCreatedByDisplayName(): string
 * @method setCreatedByDisplayName(string $displayName)
 * @method getCreatedAt(): string
 * @method setCreatedAt(string $createdAt)
 * @method getLastEditBy(): string
 * @method setLastEditBy(string $lastEditBy)
 * @method getLastEditByDisplayName(): string
 * @method setLastEditByDisplayName(string $displayName)
 * @method getLastEditAt(): string
 * @method setLastEditAt(string $lastEditAt)
 * @method getType(): string
 * @method setType(string $type)
 * @method getSubtype(): string
 * @method setSubtype(string $subtype)
 * @method getMandatory(): bool
 * @method setMandatory(?bool $mandatory)
 * @method getDescription(): string
 * @method setDescription(?string $description)
 * @method getNumberDefault(): float
 * @method setNumberDefault(?float $numberDefault)
 * @method getNumberMin(): float
 * @method setNumberMin(?float $numberMin)
 * @method getNumberMax(): float
 * @method setNumberMax(?float $numberMax)
 * @method getNumberDecimals(): int
 * @method setNumberDecimals(?int $numberDecimals)
 * @method getNumberPrefix(): string
 * @method setNumberPrefix(?string $numberPrefix)
 * @method getNumberSuffix(): string
 * @method setNumberSuffix(?string $numberSuffix)
 * @method getTextDefault(): string
 * @method setTextDefault(?string $textDefault)
 * @method getTextAllowedPattern(): string
 * @method setTextAllowedPattern(?string $textAllowedPattern)
 * @method getTextMaxLength(): int
 * @method setTextMaxLength(?int $textMaxLength)
 * @method getTextUnique(): bool
 * @method setTextUnique(?bool $textUnique)
 * @method getSelectionOptions(): string
 * @method getSelectionDefault(): string
 * @method setSelectionOptions(?string $selectionOptionsArray)
 * @method setSelectionDefault(?string $selectionDefault)
 * @method getDatetimeDefault(): string
 * @method setDatetimeDefault(?string $datetimeDefault)
 * @method getUsergroupDefault(): string
 * @method setUsergroupDefault(?string $usergroupDefaultArray)
 * @method getUsergroupMultipleItems(): bool
 * @method setUsergroupMultipleItems(?bool $usergroupMultipleItems)
 * @method getUsergroupSelectUsers(): bool
 * @method setUsergroupSelectUsers(?bool $usergroupSelectUsers)
 * @method getUsergroupSelectGroups(): bool
 * @method setUsergroupSelectGroups(?bool $usergroupSelectGroups)
 * @method getUsergroupSelectTeams(): bool
 * @method setUsergroupSelectTeams(?bool $usergroupSelectTeams)
 * @method getShowUserStatus(): bool
 * @method setShowUserStatus(?bool $showUserStatus)
 * @method getViewColumnInformation(): ViewColumnInformation
 * @method setViewColumnInformation(ViewColumnInformation $viewColumnInformation)
 * @method getCustomSettings(): ?string
 * @method setCustomSettings(?string $customSettings)
 */
class Column extends EntitySuper implements JsonSerializable {
	// Meta column types
	public const TYPE_META_ID = -1;
	public const TYPE_META_CREATED_BY = -2;
	public const TYPE_META_UPDATED_BY = -3;
	public const TYPE_META_CREATED_AT = -4;
	public const TYPE_META_UPDATED_AT = -5;
	// In case a new one is added, add it to isValidMetaTypeId() as well.
	// When we drop PHP 8.0, we can switch to enums.

	public const TYPE_SELECTION = 'selection';
	public const TYPE_TEXT = 'text';
	public const TYPE_NUMBER = 'number';
	public const TYPE_DATETIME = 'datetime';
	public const TYPE_USERGROUP = 'usergroup';

	public const SUBTYPE_DATETIME_DATE = 'date';
	public const SUBTYPE_DATETIME_TIME = 'time';

	public const SUBTYPE_SELECTION_CHECK = 'check';
	public const SUBTYPE_SELECTION_MULTI = 'selection-multi';

	public const SUBTYPE_TEXT_LINE = 'line';

	public const META_ID_TITLE = 'id';

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
	protected ?bool $textUnique = null;

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
	protected ?bool $usergroupSelectTeams = null;
	protected ?bool $showUserStatus = null;
	protected ?string $customSettings = null;

	// virtual properties
	protected ?string $createdByDisplayName = null;
	protected ?string $lastEditByDisplayName = null;
	protected ?ViewColumnInformation $viewColumnInformation = null;

	protected const VIRTUAL_PROPERTIES = ['createdByDisplayName', 'lastEditByDisplayName', 'viewColumnInformation'];

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
		$this->addType('textUnique', 'boolean');

		// // type usergroup
		$this->addType('usergroupMultipleItems', 'boolean');
		$this->addType('usergroupSelectUsers', 'boolean');
		$this->addType('usergroupSelectGroups', 'boolean');
		$this->addType('usergroupSelectTeams', 'boolean');
		$this->addType('showUserStatus', 'boolean');

		$this->addType('customSettings', 'string');
	}

	public static function isValidMetaTypeId(int $metaTypeId): bool {
		return in_array($metaTypeId, [
			self::TYPE_META_ID,
			self::TYPE_META_CREATED_BY,
			self::TYPE_META_UPDATED_BY,
			self::TYPE_META_CREATED_AT,
			self::TYPE_META_UPDATED_AT,
		], true);
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
		$column->setTextUnique($data->getTextUnique());
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
		$column->setUsergroupSelectTeams($data->getUsergroupSelectTeams());
		$column->setShowUserStatus($data->getShowUserStatus());
		$column->setCustomSettings($data->getCustomSettings());
		return $column;
	}

	public function getUsergroupDefaultArray():array {
		$default = $this->getUsergroupDefault();
		if ($default !== '' && $default !== null) {
			return \json_decode($default, true) ?? [];
		} else {
			return [];
		}
	}

	public function setUsergroupDefaultArray(array $array):void {
		$json = \json_encode($array);
		$this->setUsergroup($json);
	}

	public function getSelectionOptionsArray(): array {
		$options = $this->getSelectionOptions();
		if ($options !== '' && $options !== null && $options !== 'null') {
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
			'viewColumnInformation' => $this->viewColumnInformation?->jsonSerialize(),
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
			'textUnique' => $this->textUnique,

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
			'usergroupSelectTeams' => $this->usergroupSelectTeams,
			'showUserStatus' => $this->showUserStatus,
			'customSettings' => $this->getCustomSettingsArray() ?: new \stdClass(),
		];
	}

	public function getCustomSettingsArray(): array {
		return json_decode($this->customSettings, true) ?: [];
	}
}
