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
	) {
	}

	public static function createFromArray(array $data): self {
		return new self(
			$data['title'] ?? null,
			$data['type'] ?? null,
			$data['subtype'] ?? null,
			$data['mandatory'] ?? null,
			$data['description'] ?? null,
			$data['textDefault'] ?? null,
			$data['textAllowedPattern'] ?? null,
			$data['textMaxLength'] ?? null,
			$data['numberDefault'] ?? null,
			$data['numberMin'] ?? null,
			$data['numberMax'] ?? null,
			$data['numberDecimals'] ?? null,
			$data['numberPrefix'] ?? null,
			$data['numberSuffix'] ?? null,
			$data['selectionOptions'] ?? null,
			$data['selectionDefault'] ?? null,
			$data['datetimeDefault'] ?? null,
			$data['usergroupDefault'] ?? null,
			$data['usergroupMultipleItems'] ?? null,
			$data['usergroupSelectUsers'] ?? null,
			$data['usergroupSelectGroups'] ?? null,
			$data['usergroupSelectTeams'] ?? null,
			$data['showUserStatus'] ?? null,
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
}
