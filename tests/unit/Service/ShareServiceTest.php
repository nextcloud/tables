<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\ContextNavigationMapper;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\ValueObject\ShareCreate;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ShareServiceTest extends TestCase {
	private $permissionsService;
	private $logger;
	private $mapper;
	private $tableMapper;
	private $viewMapper;
	private $userHelper;
	private $groupHelper;
	private $circleHelper;
	private $contextNavigationMapper;
	private $dbc;
	private $secureRandom;
	private $userManager;
	private $hasher;
	private $shareManager;
	protected $shareService;
	private Share $publicShare;

	protected function setUp(): void {
		parent::setUp();
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->mapper = $this->createMock(ShareMapper::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->userHelper = $this->createMock(UserHelper::class);
		$this->groupHelper = $this->createMock(GroupHelper::class);
		$this->circleHelper = $this->createMock(CircleHelper::class);
		$this->contextNavigationMapper = $this->createMock(ContextNavigationMapper::class);
		$this->dbc = $this->createMock(IDBConnection::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->shareManager = $this->createMock(IShareManager::class);

		$this->shareService = new ShareService(
			$this->permissionsService,
			$this->logger,
			'admin',
			$this->mapper,
			$this->tableMapper,
			$this->viewMapper,
			$this->userHelper,
			$this->groupHelper,
			$this->circleHelper,
			$this->contextNavigationMapper,
			$this->dbc,
			$this->secureRandom,
			$this->userManager,
			$this->hasher,
			$this->shareManager
		);

		$this->publicShare = new Share();
		$this->publicShare->setSender('userA');
	}

	public function testCreateContextShareSetsAllPermissionsFalse(): void {
		$this->mapper->method('insert')->willReturnCallback(function (Share $share) {
			return $share;
		});

		$share = $this->shareService->create(
			new ShareCreate(
				1,
				'context',
				'user1',
				'user',
				true, true, true, true, true,
				0
			)
		);

		$this->assertFalse($share->getPermissionRead());
		$this->assertFalse($share->getPermissionCreate());
		$this->assertFalse($share->getPermissionUpdate());
		$this->assertFalse($share->getPermissionDelete());
		$this->assertFalse($share->getPermissionManage());
	}

	public function testUpdatePermissionThrowsOnContextShare(): void {
		$share = new Share();
		$share->setNodeType('context');
		$share->setNodeId(9);
		$this->mapper->method('find')->willReturn($share);

		$this->expectException(PermissionError::class);
		$this->shareService->updatePermission(1, ['manage' => true]);
	}

	public function testIsPublicShareAccessibleReturnsTrueWhenSenderCanShare(): void {
		$senderUser = $this->createMock(IUser::class);
		$senderUser->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->with('userA')->willReturn($senderUser);
		$this->shareManager->method('sharingDisabledForUser')->with('userA')->willReturn(false);

		$this->assertTrue($this->shareService->isPublicShareAccessible($this->publicShare));
	}

	public function testIsPublicShareAccessibleReturnsFalseWhenSenderUserDisabled(): void {
		$senderUser = $this->createMock(IUser::class);
		$senderUser->method('isEnabled')->willReturn(false);
		$this->userManager->method('get')->with('userA')->willReturn($senderUser);

		$this->assertFalse($this->shareService->isPublicShareAccessible($this->publicShare));
	}

	public function testAssertPublicShareAccessibleThrowsWhenSenderCannotShare(): void {
		$senderUser = $this->createMock(IUser::class);
		$senderUser->method('isEnabled')->willReturn(false);
		$this->userManager->method('get')->with('userA')->willReturn($senderUser);

		$this->expectException(PermissionError::class);
		$this->shareService->assertPublicShareAccessible($this->publicShare);
	}

	public function testDeleteForShareReviewCallsSideEffects(): void {
		$share = new Share();
		$share->setId(42);
		$share->setNodeType('table');
		$this->mapper->expects($this->once())
			->method('find')
			->with(42)
			->willReturn($share);
		$this->mapper->expects($this->once())
			->method('delete')
			->with($share);
		$this->contextNavigationMapper->expects($this->never())->method('deleteByShareId');

		$this->shareService->deleteForShareReview(42);
	}

	public function testDeleteForShareReviewCleansUpContextNavigation(): void {
		$share = new Share();
		$share->setId(7);
		$share->setNodeType('context');
		$this->mapper->expects($this->once())
			->method('find')
			->with(7)
			->willReturn($share);
		$this->mapper->expects($this->once())
			->method('delete')
			->with($share);
		$this->contextNavigationMapper->expects($this->once())
			->method('deleteByShareId')
			->with(7);

		$this->shareService->deleteForShareReview(7);
	}

	public function testDeleteForShareReviewPropagatesDoesNotExist(): void {
		$this->mapper->expects($this->once())
			->method('find')
			->with(99)
			->willThrowException(new DoesNotExistException(''));

		$this->expectException(DoesNotExistException::class);
		$this->shareService->deleteForShareReview(99);
	}

	public function testDeleteForShareReviewPropagatesDbException(): void {
		$share = new Share();
		$share->setId(5);
		$share->setNodeType('table');
		$this->mapper->method('find')->willReturn($share);
		$this->mapper->method('delete')->willThrowException($this->createMock(Exception::class));

		$this->expectException(Exception::class);
		$this->shareService->deleteForShareReview(5);
	}
}
