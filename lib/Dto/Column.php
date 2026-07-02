<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Dto;

class Column {
	public function __construct(
		private readonly ?string $title = null,
		private readonly ?string $type = null,
		private readonly ?string $subtype = null,
		private readonly ?bool $mandatory = null,
		private readonly ?string $description = null,
		private readonly ?string $textDefault = null,
		private readonly ?string $textAllowedPattern = null,
		private readonly ?int $textMaxLength = null,
		private readonly ?bool $textUnique = null,
		private readonly ?float $numberDefault = null,
		private readonly ?float $numberMin = null,
		private readonly ?float $numberMax = null,
		private readonly ?int $numberDecimals = null,
		private readonly ?string $numberPrefix = null,
		private readonly ?string $numberSuffix = null,
		private readonly ?string $selectionOptions = null,
		private readonly ?string $selectionDefault = null,
		private readonly ?string $datetimeDefault = null,
		private readonly ?string $usergroupDefault = null,
		private readonly ?bool $usergroupMultipleItems = null,
		private readonly ?bool $usergroupSelectUsers = null,
		private readonly ?bool $usergroupSelectGroups = null,
		private readonly ?bool $usergroupSelectTeams = null,
		private readonly ?bool $showUserStatus = null,
		private readonly ?string $customSettings = null,
	) {
	}

	public static function createFromArray(array $data): self {
		$customSettings = $data['customSettings'] ?? null;
		if (is_array($customSettings)) {
			$customSettings = json_encode($customSettings);
		}

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
			customSettings: $customSettings,
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
