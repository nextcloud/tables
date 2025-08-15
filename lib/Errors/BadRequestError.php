<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Errors;

class BadRequestError extends \Exception {
	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
		public ?string $translatedMessage = null,
	) {
		parent::__construct($message, $code, $previous);
	}
}
