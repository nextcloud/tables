<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCA\Tables\Validation\TechnicalNameValidator;
use OCP\AppFramework\Db\Entity;

/**
 * @method getContextId(): int
 * @method setContextId(int $value): void
 * @method getPageType(): string
 * @method setPageType(string $value): void
 * @method getTechnicalName(): ?string
 * @method setTechnicalName(?string $value): void
 * @method getTitle(): ?string
 * @method setTitle(?string $value): void
 * @method getIcon(): ?string
 * @method setIcon(?string $value): void
 * @method getDescription(): ?string
 * @method setDescription(?string $value): void
 * @method getVersion(): int
 * @method setVersion(int $value): void
 */
class Page extends Entity implements \JsonSerializable {
	public const TYPE_STARTPAGE = 'startpage';
	public const TYPE_GRID = 'grid';
	public const START_PAGE_NAME = 'dashboard';
	public const DEFAULT_NAME_PREFIX = 'page_';

	protected ?int $contextId = null;
	protected ?string $pageType = null;
	protected ?string $technicalName = null;
	protected ?string $title = null;
	protected ?string $icon = null;
	protected ?string $description = null;
	protected ?int $version = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('version', 'integer');
	}

	/**
	 * Whether a technical name has the reserved form of the automatic `page_<id>`.
	 */
	public static function isDefaultName(string $technicalName): bool {
		return TechnicalNameValidator::isDefaultName($technicalName, self::DEFAULT_NAME_PREFIX);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'contextId' => $this->getContextId(),
			'pageType' => $this->getPageType(),
			'technicalName' => $this->getTechnicalName(),
			'title' => $this->getTitle(),
			'icon' => $this->getIcon(),
			'description' => $this->getDescription(),
			'version' => (int)$this->getVersion(),
		];
	}
}
