<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Errors\BadRequestError;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class TextLineBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function __construct(
		LoggerInterface $logger,
		private Row2Mapper $row2Mapper,
		private IL10N $n,
	) {
		parent::__construct($logger);
	}

	public function validateValue(mixed $value, Column $column, string $userId, int $tableId, ?int $rowId): void {
		if (!$column->getTextUnique()) {
			return;
		}

		$filter = [[['columnId' => $column->getId(), 'operator' => 'is-equal', 'value' => $value]]];
		$alreadyExistentRows = $this->row2Mapper->findAll([$column->getId()], $tableId, 2, filter: $filter, userId: $userId);
		foreach ($alreadyExistentRows as $alreadyExistentRow) {
			if ($alreadyExistentRow->getId() === $rowId) {
				continue;
			}
			throw new BadRequestError(
				'Column "' . $column->getTitle() . '" contains a non-unique value.',
				translatedMessage: $this->n->t(
					'Column "%s" contains a non-unique value.',
					[$column->getTitle()]
				),
			);
		}
	}
}
