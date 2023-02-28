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

use OCA\Tables\Errors\InternalError;
use Test\TestCase;

class PermissionsServiceTest extends TestCase {
	private PermissionsService $permissionsService;

	public function setUp(): void {
		parent::setUp();
		$this->permissionsService = $this->createMock(PermissionsService::class);
	}

	public function testPreCheckUserIdGivenUser() {
		$this->permissionsService
			->expects(self::any())
			->method('preCheckUserId')
			->willReturnCallback(function (&$userId, $canBeEmpty): bool {
				self::assertEquals('bar', $userId);
				return true;
			});

		$userId = 'foo';
		self::assertNull($this->permissionsService->preCheckUserId($userId, true));
	}
}
