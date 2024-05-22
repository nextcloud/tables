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

use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Helper\UserHelper;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class PermissionsServiceTest extends TestCase {
	protected function getPermissionServiceWithUserId(mixed $userId, bool $isCli = false): PermissionsService {
		$logger = $this->createMock(LoggerInterface::class);
		$tableMapper = $this->createMock(TableMapper::class);
		$shareMapper = $this->createMock(ShareMapper::class);
		$viewMapper = $this->createMock(ViewMapper::class);
		$contextMapper = $this->createMock(ContextMapper::class);
		$userHelper = $this->createMock(UserHelper::class);

		return new PermissionsService($logger, $userId, $tableMapper, $viewMapper, $shareMapper, $contextMapper, $userHelper, $isCli);
	}

	public function testPreCheckUserIdGivenUser() {
		$userId = 'TestUser';
		$permissionsService = $this->getPermissionServiceWithUserId($userId);

		self::assertEquals($userId, $permissionsService->preCheckUserId($userId));
	}

	public function testPreCheckUserIdNoUser() {
		$userId = null;
		$permissionsService = $this->getPermissionServiceWithUserId($userId);

		self::expectException(InternalError::class);
		$permissionsService->preCheckUserId($userId);
	}

	public function testPreCheckUserIdNoUserButContext() {
		$userId = 'john';
		$permissionsService = $this->getPermissionServiceWithUserId($userId);

		self::assertEquals($userId, $permissionsService->preCheckUserId(null));
	}

	public function testPreCheckUserIdNoUserNotAllowed() {
		$userId = '';
		$permissionsService = $this->getPermissionServiceWithUserId($userId);

		self::expectException(InternalError::class);
		$permissionsService->preCheckUserId($userId, false);

		self::expectException(InternalError::class);
		$permissionsService->preCheckUserId($userId, true);
	}

	public function testPreCheckUserIdNoUserAllowed() {
		$userId = '';
		$permissionsService = $this->getPermissionServiceWithUserId($userId, true);

		self::assertEquals($userId, $permissionsService->preCheckUserId($userId));
	}
}
