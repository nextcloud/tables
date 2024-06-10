<?php

namespace OCA\Tables\Middleware\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RequirePermission {
	public function __construct(
		protected int $permission,
		protected ?int $type = null,
		protected string $typeParam = 'nodeType',
		protected string $idParam = 'nodeId',
	) {
	}

	public function getPermission(): int {
		return $this->permission;
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
