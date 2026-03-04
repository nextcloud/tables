<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Constants\UsergroupType;
use OCA\Tables\Db\Column;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Helper\CircleHelper;
use OCP\IGroupManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UsergroupBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function __construct(
		protected LoggerInterface $logger,
		protected CircleHelper $circleHelper,
		protected IUserManager $userManager,
		protected IGroupManager $groupManager,
	) {
		parent::__construct($logger);
	}

	/**
	 * @param mixed $value
	 * @param Column $column
	 * @param string $userId
	 * @param int $tableId
	 * @param int|null $rowId
	 *
	 * @throws BadRequestError
	 */
	public function validateValue(mixed $value, Column $column, string $userId, int $tableId, ?int $rowId): void {
		if ($value === null) {
			return;
		}

		if (is_string($value)) {
			$value = json_decode($value, true);
		}

		if (!is_array($value)) {
			throw new BadRequestError('Invalid value for usergroup column');
		}

		foreach ($value as $userGroupEntry) {
			if (!isset($userGroupEntry['id']) || !is_string($userGroupEntry['id'])) {
				throw new BadRequestError('Invalid value for usergroup id');
			}
			if (!isset($userGroupEntry['type']) || !is_int($userGroupEntry['type'])) {
				throw new BadRequestError('Invalid usergroup type');
			}
			if ($userGroupEntry['type'] === UsergroupType::USER) {
				if (!$this->userManager->get($userGroupEntry['id'])) {
					throw new BadRequestError('User not found');
				}
			}
			if ($userGroupEntry['type'] === UsergroupType::GROUP) {
				if (!$this->groupManager->get($userGroupEntry['id'])) {
					throw new BadRequestError('Group not found');
				}
			}
			if ($userGroupEntry['type'] === UsergroupType::CIRCLE) {
				if (!$this->circleHelper->circleExists($userGroupEntry['id'], $userId)) {
					throw new BadRequestError('Circle not found');
				}
			}
		}
	}

	/**
	 * Parse frontend value (array) and transform for using it in the database (array)
	 * Uses json encode just because wrapping methods currently all assume that
	 *
	 * Used when inserting from API to the database
	 *
	 * Why not use Mapper::parseValueIncoming
	 *
	 * @param mixed $value (array|string|null)
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		if (!$column) {
			$this->logger->warning('No column given, but expected on ' . __FUNCTION__ . ' within ' . __CLASS__, ['exception' => new \Exception()]);
			return json_encode([]);
		}

		if ($value === null) {
			return json_encode([]);
		}

		return json_encode($value);
	}

	/**
	 * @param mixed $value parsable is json encoded array{id: string, type: int}
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		if (!$column) {
			$this->logger->warning('No column given, but expected on ' . __FUNCTION__ . ' within ' . __CLASS__, ['exception' => new \Exception()]);
			return false;
		}

		if ($value === null) {
			return true;
		}

		if (is_string($value)) {
			$value = json_decode($value, true);
		}

		foreach ($value as $v) {
			if ((array_key_exists('id', $v) && !is_string($v['id'])) && (array_key_exists('type', $v) && !is_int($v['type']))) {
				return false;
			}
		}

		return true;
	}
}
