<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\UserHelper;
use PHPUnit\Framework\MockObject\MockObject;
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
		$circleHelper = $this->createMock(CircleHelper::class);

		return new PermissionsService($logger, $userId, $tableMapper, $viewMapper, $shareMapper, $contextMapper, $userHelper, $circleHelper, $isCli);
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

	/**
	 * @dataProvider relationTargetTypeProvider
	 */
	public function testCanReadRelationTargetChecksRowReadPermission(string $relationType) {
		$permissionsService = $this->getPermissionServiceWithMockedRowReadCheck();
		$permissionsService->expects(self::once())
			->method('canReadRowsByElementId')
			->with(5, $relationType, 'user1')
			->willReturn(true);

		self::assertTrue($permissionsService->canReadRelationTarget($relationType, 5, 'user1'));
	}

	public static function relationTargetTypeProvider(): array {
		return [
			'table' => ['table'],
			'view' => ['view'],
		];
	}

	public function testCanReadRelationTargetRejectsUnknownType() {
		$permissionsService = $this->getPermissionServiceWithMockedRowReadCheck();
		$permissionsService->expects(self::never())
			->method('canReadRowsByElementId');

		self::assertFalse($permissionsService->canReadRelationTarget('context', 5, 'user1'));
	}

	protected function getPermissionServiceWithMockedRowReadCheck(): PermissionsService&MockObject {
		return $this->getMockBuilder(PermissionsService::class)
			->setConstructorArgs([
				$this->createMock(LoggerInterface::class),
				'user1',
				$this->createMock(TableMapper::class),
				$this->createMock(ViewMapper::class),
				$this->createMock(ShareMapper::class),
				$this->createMock(ContextMapper::class),
				$this->createMock(UserHelper::class),
				$this->createMock(CircleHelper::class),
				false,
			])
			->onlyMethods(['canReadRowsByElementId'])
			->getMock();
	}
}
