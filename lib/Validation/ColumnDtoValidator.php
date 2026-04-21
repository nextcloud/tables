<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Validation;

use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\BadRequestError;

class ColumnDtoValidator {
	private const TECHNICAL_NAME_MAX_LENGTH = 200;

	/**
	 * reserved keys to avoid confusion with meta fields and common row keys.
	 */
	private const RESERVED_TECHNICAL_NAMES = [
		'id',
		'created_by',
		'created_at',
		'last_edit_by',
		'last_edit_at',
		'data',
	];

	/**
	 * @throws BadRequestError
	 */
	public function validate(ColumnDto $columnDto): void {
		$this->validateTechnicalName($columnDto);

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

	/**
	 * @throws BadRequestError
	 */
	private function validateTechnicalName(ColumnDto $columnDto): void {
		$technicalName = $columnDto->getTechnicalName();
		if ($technicalName === null) {
			return;
		}

		if ($technicalName === '') {
			throw new BadRequestError('Technical name must not be empty.');
		}

		if (strlen($technicalName) > self::TECHNICAL_NAME_MAX_LENGTH) {
			throw new BadRequestError('Technical name must be maximum ' . self::TECHNICAL_NAME_MAX_LENGTH . ' characters long.');
		}

		if (in_array($technicalName, self::RESERVED_TECHNICAL_NAMES, true)) {
			throw new BadRequestError('Technical name is reserved.');
		}

		if (preg_match('/^[a-z][a-z0-9_]*$/', $technicalName) !== 1) {
			throw new BadRequestError('Technical name must start with a letter and contain only lowercase letters, digits, and underscores.');
		}
	}
}
