<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Validation;

use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\BadRequestError;

class ColumnDtoValidator {
	/**
	 * @throws BadRequestError
	 */
	public function validate(ColumnDto $columnDto): void {
		$textMaxLength = $columnDto->getTextMaxLength();
		if ($textMaxLength !== null && $textMaxLength < 0) {
			throw new BadRequestError('Maximum text length must be greater than or equal to 0.');
		}

		$numberMin = $columnDto->getNumberMin();
		$numberMax = $columnDto->getNumberMax();
		if ($numberMin !== null && $numberMax !== null && $numberMin > $numberMax) {
			throw new BadRequestError('Minimum number must be less than or equal to maximum number.');
		}
	}
}
