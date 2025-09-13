<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Dto;

class Column {
	public function __construct(
		private ?string $title = null,
		private ?string $type = null,
		private ?string $subtype = null,
		private ?bool $mandatory = null,
		private ?string $description = null,
		private ?string $textDefault = null,
		private ?string $textAllowedPattern = null,
		private ?int $textMaxLength = null,
		private ?bool $textUnique = null,
		private ?float $numberDefault = null,
		private ?float $numberMin = null,
		private ?float $numberMax = null,
		private ?int $numberDecimals = null,
		private ?string $numberPrefix = null,
		private ?string $numberSuffix = null,
		private ?string $selectionOptions = null,
		private ?string $selectionDefault = null,
		private ?string $datetimeDefault = null,
		private ?string $usergroupDefault = null,
		private ?bool $usergroupMultipleItems = null,
		private ?bool $usergroupSelectUsers = null,
		private ?bool $usergroupSelectGroups = null,
		private ?bool $usergroupSelectTeams = null,
		private ?bool $showUserStatus = null,
		private ?string $customSettings = null,
	) {
	}

	public static function createFromArray(array $data): self {
		return new self(
			title: $data['title'] ?? null,
			type: $data['type'] ?? null,
			subtype: $data['subtype'] ?? null,
			mandatory: $data['mandatory'] ?? null,
			description: $data['description'] ?? null,
			textDefault: $data['textDefault'] ?? null,
			textAllowedPattern: $data['textAllowedPattern'] ?? null,
			textMaxLength: $data['textMaxLength'] ?? null,
			textUnique: $data['textUnique'] ?? null,
			numberDefault: $data['numberDefault'] ?? null,
			numberMin: $data['numberMin'] ?? null,
			numberMax: $data['numberMax'] ?? null,
			numberDecimals: $data['numberDecimals'] ?? null,
			numberPrefix: $data['numberPrefix'] ?? null,
			numberSuffix: $data['numberSuffix'] ?? null,
			selectionOptions: $data['selectionOptions'] ?? null,
			selectionDefault: $data['selectionDefault'] ?? null,
			datetimeDefault: $data['datetimeDefault'] ?? null,
			usergroupDefault: $data['usergroupDefault'] ?? null,
			usergroupMultipleItems: $data['usergroupMultipleItems'] ?? null,
			usergroupSelectUsers: $data['usergroupSelectUsers'] ?? null,
			usergroupSelectGroups: $data['usergroupSelectGroups'] ?? null,
			usergroupSelectTeams: $data['usergroupSelectTeams'] ?? null,
			showUserStatus: $data['showUserStatus'] ?? null,
			customSettings: $data['customSettings'] ?? null,
		);
	}

	public function getType(): ?string {
		return $this->type;
	}

	public function getSubtype(): ?string {
		return $this->subtype;
	}

	public function isMandatory(): ?bool {
		return $this->mandatory;
	}

	public function getTitle(): ?string {
		return $this->title;
	}

	public function getDescription(): ?string {
		return $this->description;
	}

	public function getTextDefault(): ?string {
		return $this->textDefault;
	}

	public function getTextAllowedPattern(): ?string {
		return $this->textAllowedPattern;
	}

	public function getTextMaxLength(): ?int {
		return $this->textMaxLength;
	}

	public function getTextUnique(): ?bool {
		return $this->textUnique;
	}

	public function getNumberDefault(): ?float {
		return $this->numberDefault;
	}

	public function getNumberMin(): ?float {
		return $this->numberMin;
	}

	public function getNumberMax(): ?float {
		return $this->numberMax;
	}

	public function getNumberDecimals(): ?int {
		return $this->numberDecimals;
	}

	public function getNumberPrefix(): ?string {
		return $this->numberPrefix;
	}

	public function getNumberSuffix(): ?string {
		return $this->numberSuffix;
	}

	public function getSelectionOptions(): ?string {
		return $this->selectionOptions;
	}

	public function getSelectionDefault(): ?string {
		return $this->selectionDefault;
	}

	public function getDatetimeDefault(): ?string {
		return $this->datetimeDefault;
	}

	public function getUsergroupDefault(): ?string {
		return $this->usergroupDefault;
	}

	public function getUsergroupMultipleItems(): ?bool {
		return $this->usergroupMultipleItems;
	}

	public function getUsergroupSelectUsers(): ?bool {
		return $this->usergroupSelectUsers;
	}

	public function getUsergroupSelectGroups(): ?bool {
		return $this->usergroupSelectGroups;
	}

	public function getUsergroupSelectTeams(): ?bool {
		return $this->usergroupSelectTeams;
	}

	public function getShowUserStatus(): ?bool {
		return $this->showUserStatus;
	}

	public function getCustomSettings(): ?string {
		return $this->customSettings;
	}
}
