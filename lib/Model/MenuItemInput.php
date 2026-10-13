<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use OCA\Tables\Db\MenuItem;

class MenuItemInput {
	public const MAX_LABEL_LENGTH = 200;
	public const MAX_ICON_LENGTH = 64;
	public const MAX_URL_LENGTH = 2000;
	public const MAX_TECHNICAL_NAME_LENGTH = 200;
	public const TECHNICAL_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';
	private const URL_SCHEMES = ['http', 'https'];

	/**
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		private readonly string $label,
		private readonly ?string $icon,
		private readonly string $targetType,
		private readonly ?int $targetId,
		private readonly ?string $url,
		private readonly ?string $technicalName,
		private readonly ?int $parentIndex = null,
	) {
		if (trim($this->label) === '' || mb_strlen($this->label) > self::MAX_LABEL_LENGTH) {
			throw new InvalidArgumentException('A menu item needs a label of at most ' . self::MAX_LABEL_LENGTH . ' characters.');
		}
		if ($this->icon !== null && mb_strlen($this->icon) > self::MAX_ICON_LENGTH) {
			throw new InvalidArgumentException('A menu item icon name must not exceed ' . self::MAX_ICON_LENGTH . ' characters.');
		}
		if (!in_array($this->targetType, MenuItem::TARGET_TYPES, true)) {
			throw new InvalidArgumentException('Unexpected menu item target type, expected one of: ' . implode(', ', MenuItem::TARGET_TYPES) . '.');
		}
		if ($this->targetType === MenuItem::TARGET_URL) {
			self::assertUrlValid($this->url);
		} elseif ($this->targetId === null || $this->targetId <= 0) {
			throw new InvalidArgumentException('A menu item pointing at a ' . $this->targetType . ' needs its id.');
		}
		if ($this->technicalName !== null) {
			self::assertTechnicalNameValid($this->technicalName);
		}
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function createFromInputArray(mixed $data): self {
		if (!is_array($data)) {
			throw new InvalidArgumentException('A menu item must be an object.');
		}
		if (!is_string($data['label'] ?? null)) {
			throw new InvalidArgumentException('A menu item needs a label.');
		}
		$targetType = $data['targetType'] ?? MenuItem::TARGET_URL;
		if (!is_string($targetType)) {
			throw new InvalidArgumentException('The target type of a menu item must be a string.');
		}
		$isUrl = $targetType === MenuItem::TARGET_URL;
		$targetId = $data['targetId'] ?? null;
		if (!$isUrl && !is_int($targetId)) {
			throw new InvalidArgumentException('A menu item pointing at a ' . $targetType . ' needs its id.');
		}
		foreach (['icon', 'url', 'technicalName'] as $optional) {
			if (isset($data[$optional]) && !is_string($data[$optional])) {
				throw new InvalidArgumentException('The menu item property "' . $optional . '" must be a string.');
			}
		}
		$parentIndex = $data['parentIndex'] ?? null;
		if ($parentIndex !== null && (!is_int($parentIndex) || $parentIndex < 0)) {
			throw new InvalidArgumentException('The parent of a menu item is given as the position of that parent in the menu.');
		}

		return new self(
			label: trim($data['label']),
			icon: self::emptyToNull($data['icon'] ?? null),
			targetType: $targetType,
			targetId: $isUrl ? null : $targetId,
			url: $isUrl ? trim((string)($data['url'] ?? '')) : null,
			technicalName: self::emptyToNull($data['technicalName'] ?? null),
			parentIndex: $parentIndex,
		);
	}

	/**
	 * A menu with one level of nesting: an item may sit under a top-level item of the same menu.
	 *
	 * @return list<self>
	 * @throws InvalidArgumentException
	 */
	public static function createListFromInputArray(mixed $data): array {
		if (!is_array($data) || !array_is_list($data)) {
			throw new InvalidArgumentException('The menu items must be a list.');
		}
		$items = array_map(self::createFromInputArray(...), $data);
		foreach ($items as $item) {
			$parentIndex = $item->getParentIndex();
			if ($parentIndex === null) {
				continue;
			}
			if (!isset($items[$parentIndex]) || $items[$parentIndex]->getParentIndex() !== null) {
				throw new InvalidArgumentException('A menu item can only sit under a top-level item of the same menu.');
			}
		}
		return $items;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function assertTechnicalNameValid(string $technicalName): void {
		if (strlen($technicalName) > self::MAX_TECHNICAL_NAME_LENGTH || !preg_match(self::TECHNICAL_NAME_PATTERN, $technicalName)) {
			throw new InvalidArgumentException('A technical name must start with a lowercase letter and contain only lowercase letters, numbers and underscores.');
		}
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private static function assertUrlValid(?string $url): void {
		$scheme = $url === null ? null : parse_url($url, PHP_URL_SCHEME);
		if ($url === null || mb_strlen($url) > self::MAX_URL_LENGTH
			|| !is_string($scheme) || !in_array(strtolower($scheme), self::URL_SCHEMES, true)
			|| parse_url($url, PHP_URL_HOST) === null) {
			throw new InvalidArgumentException('A menu item link must be an http or https address of at most ' . self::MAX_URL_LENGTH . ' characters.');
		}
	}

	private static function emptyToNull(?string $value): ?string {
		if ($value === null) {
			return null;
		}
		$value = trim($value);
		return $value === '' ? null : $value;
	}

	public function getLabel(): string {
		return $this->label;
	}

	public function getIcon(): ?string {
		return $this->icon;
	}

	public function getTargetType(): string {
		return $this->targetType;
	}

	public function getTargetId(): ?int {
		return $this->targetId;
	}

	public function getUrl(): ?string {
		return $this->url;
	}

	public function getTechnicalName(): ?string {
		return $this->technicalName;
	}

	/**
	 * The position of the parent item in the same menu, or null for a top-level item.
	 */
	public function getParentIndex(): ?int {
		return $this->parentIndex;
	}
}
