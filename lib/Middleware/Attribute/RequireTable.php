<?php

declare(strict_types=1);

namespace OCA\Tables\Middleware\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RequireTable {
	public function __construct(
		protected bool $enhance = false,
	) {
	}

	public function enhance(): bool {
		return $this->enhance;
	}
}
