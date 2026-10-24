<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use OCA\Tables\Db\MenuItem;
use OCA\Tables\Helper\IconHelper;
use OCA\Tables\Validation\TechnicalNameValidator;

/**
 * A submitted menu item, checked on its own: its label, icon, description, target,
 * technical name and the position of its parent. Whether a target may be used is up to the application it is saved in.
 */
class MenuItemInput {
	public const MAX_LABEL_LENGTH = 200;
	public const MAX_DESCRIPTION_LENGTH = 2000;
	public const MAX_URL_LENGTH = 2000;
	private const URL_SCHEMES = ['http', 'https'];

	/**
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		private readonly string $label,
		private readonly ?string $icon,
		private readonly ?string $description,
		private readonly string $targetType,
		private readonly ?int $targetId,
		private readonly ?string $url,
		private readonly ?string $technicalName,
		private readonly ?int $parentIndex = null,
		private readonly bool $allowNewStartPage = false,
	) {
		if (trim($this->label) === '' || mb_strlen($this->label) > self::MAX_LABEL_LENGTH) {
			throw new InvalidArgumentException('A menu item needs a label of at most ' . self::MAX_LABEL_LENGTH . ' characters.');
		}
		if ($this->icon !== null && !IconHelper::isValid($this->icon)) {
			throw new InvalidArgumentException('A menu item has an unknown icon: ' . $this->icon);
		}
		if ($this->description !== null && mb_strlen($this->description) > self::MAX_DESCRIPTION_LENGTH) {
			throw new InvalidArgumentException('A menu item description has at most ' . self::MAX_DESCRIPTION_LENGTH . ' characters.');
		}
		if (!in_array($this->targetType, MenuItem::TARGET_TYPES, true)) {
			throw new InvalidArgumentException('Unexpected menu item target type, expected one of: ' . implode(', ', MenuItem::TARGET_TYPES) . '.');
		}
		if ($this->targetType === MenuItem::TARGET_URL) {
			self::assertUrlValid($this->url);
		} elseif (!$this->pointsAtNewStartPage() && ($this->targetId === null || $this->targetId <= 0)) {
			throw new InvalidArgumentException('A menu item pointing at a ' . $this->targetType . ' needs its id.');
		}
		if ($this->technicalName !== null && !TechnicalNameValidator::isValid($this->technicalName)) {
			throw new InvalidArgumentException('A technical name has 1 to ' . TechnicalNameValidator::MAX_LENGTH . ' lower case letters, digits and underscores and starts with a letter.');
		}
	}

	/**
	 * @param bool $allowNewStartPage accept a page item without an id, meaning the start page
	 *                                of an application that is being created
	 * @throws InvalidArgumentException
	 */
	public static function createFromInputArray(mixed $data, bool $allowNewStartPage = false): self {
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
		$isNewStartPage = $allowNewStartPage && $targetType === MenuItem::TARGET_PAGE && $targetId === null;
		if (!$isUrl && !$isNewStartPage && !is_int($targetId)) {
			throw new InvalidArgumentException('A menu item pointing at a ' . $targetType . ' needs its id.');
		}
		foreach (['icon', 'description', 'url', 'technicalName'] as $optional) {
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
			description: self::emptyToNull($data['description'] ?? null),
			targetType: $targetType,
			targetId: $isUrl ? null : $targetId,
			url: $isUrl ? trim((string)($data['url'] ?? '')) : null,
			technicalName: self::emptyToNull($data['technicalName'] ?? null),
			parentIndex: $parentIndex,
			allowNewStartPage: $allowNewStartPage,
		);
	}

	/**
	 * A menu with one level of nesting: an item may sit under a top-level item of the
	 * same menu, named by its position.
	 *
	 * @param bool $allowNewStartPage see createFromInputArray()
	 * @return list<self>
	 * @throws InvalidArgumentException
	 */
	public static function createListFromInputArray(mixed $data, bool $allowNewStartPage = false): array {
		if (!is_array($data) || !array_is_list($data)) {
			throw new InvalidArgumentException('The menu items must be a list.');
		}
		$items = array_map(static fn (mixed $item): self => self::createFromInputArray($item, $allowNewStartPage), $data);
		foreach ($items as $item) {
			$parentIndex = $item->getParentIndex();
			if ($parentIndex !== null && (!isset($items[$parentIndex]) || $items[$parentIndex]->getParentIndex() !== null)) {
				throw new InvalidArgumentException('A menu item can only sit under a top-level item of the same menu.');
			}
		}
		return $items;
	}

	/**
	 * True for a page item without an id on the menu of an application that is being created.
	 */
	public function pointsAtNewStartPage(): bool {
		return $this->allowNewStartPage && $this->targetType === MenuItem::TARGET_PAGE && $this->targetId === null;
	}

	/**
	 * This item pointing at the given page.
	 *
	 * @throws InvalidArgumentException
	 */
	public function withPageId(int $pageId): self {
		return new self($this->label, $this->icon, $this->description, MenuItem::TARGET_PAGE, $pageId, null, $this->technicalName, $this->parentIndex);
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private static function assertUrlValid(?string $url): void {
		$scheme = $url === null ? null : parse_url($url, PHP_URL_SCHEME);
		// parse_url accepts spaces and control characters anywhere in a link
		if ($url === null || mb_strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\s\p{Z}\p{Cc}]/u', $url) !== 0
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

	public function getDescription(): ?string {
		return $this->description;
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
