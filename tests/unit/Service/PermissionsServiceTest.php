<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */


namespace OCA\Tables\Service;

use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Helper\UserHelper;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class PermissionsServiceTest extends TestCase {
	public function testPreCheckUserIdGivenUser() {
		$logger = $this->createMock(LoggerInterface::class);
		$userId = "TestUser";
		$tableMapper = $this->createMock(TableMapper::class);
		$shareMapper = $this->createMock(ShareMapper::class);
		$userHelper = $this->createMock(UserHelper::class);
		$permissionsService = new PermissionsService($logger, $userId, $tableMapper, $shareMapper, $userHelper, false);

		self::assertEquals($userId, $permissionsService->preCheckUserId($userId));
	}

	public function testPreCheckUserIdNoUser() {
		$logger = $this->createMock(LoggerInterface::class);
		$userId = null;
		$tableMapper = $this->createMock(TableMapper::class);
		$shareMapper = $this->createMock(ShareMapper::class);
		$userHelper = $this->createMock(UserHelper::class);
		$permissionsService = new PermissionsService($logger, $userId, $tableMapper, $shareMapper, $userHelper, false);

		self::expectException(InternalError::class);
		$permissionsService->preCheckUserId($userId);
	}

	public function testPreCheckUserIdNoUserButContext() {
		$logger = $this->createMock(LoggerInterface::class);
		$userId = 'john';
		$tableMapper = $this->createMock(TableMapper::class);
		$shareMapper = $this->createMock(ShareMapper::class);
		$userHelper = $this->createMock(UserHelper::class);
		$permissionsService = new PermissionsService($logger, $userId, $tableMapper, $shareMapper, $userHelper, false);

		self::assertEquals($userId, $permissionsService->preCheckUserId(null));
	}

	public function testPreCheckUserIdNoUserNotAllowed() {
		$logger = $this->createMock(LoggerInterface::class);
		$userId = '';
		$tableMapper = $this->createMock(TableMapper::class);
		$shareMapper = $this->createMock(ShareMapper::class);
		$userHelper = $this->createMock(UserHelper::class);
		$permissionsService = new PermissionsService($logger, $userId, $tableMapper, $shareMapper, $userHelper, false);

		self::expectException(InternalError::class);
		$permissionsService->preCheckUserId($userId, false);

		self::expectException(InternalError::class);
		$permissionsService->preCheckUserId($userId, true);
	}

	public function testPreCheckUserIdNoUserAllowed() {
		$logger = $this->createMock(LoggerInterface::class);
		$userId = '';
		$tableMapper = $this->createMock(TableMapper::class);
		$shareMapper = $this->createMock(ShareMapper::class);
		$userHelper = $this->createMock(UserHelper::class);
		$permissionsService = new PermissionsService($logger, $userId, $tableMapper, $shareMapper, $userHelper, true);

		self::assertEquals($userId, $permissionsService->preCheckUserId($userId));
	}
}
