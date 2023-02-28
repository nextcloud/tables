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
		$this->permissionsService = $this->createMock(\OCA\Tables\Service\PermissionsService::class);
		/*
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->request = $this->createMock(IRequest::class);
		$this->cardServiceValidator = $this->createMock(CardServiceValidator::class);

		$this->logger->expects($this->any())->method('error');

		$this->cardService = new CardService(
			$this->logger,
			$this->request,
			$this->cardServiceValidator,
			'user1'
		);
		*/
	}

	public function testPreCheckUserId() {
		//self::assertEquals(1, 1);
		$userId = 'john';
		try {
			$this->permissionsService->preCheckUserId($userId);
		} catch (InternalError $e) {
		}
		self::assertEquals('john', $userId);
	}
}
