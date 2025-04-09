<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Middleware\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RequireEntity {
	public function __construct(
		protected ?int $type = null,
		protected string $typeParam = 'entityType',
		protected string $idParam = 'entityId',
	) {
	}

	public function getTypeParam(): string {
		return $this->typeParam;
	}

	public function getIdParam(): string {
		return $this->idParam;
	}

	public function getType(): ?int {
		return $this->type;
	}
}
