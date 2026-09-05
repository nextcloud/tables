<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\UserArchive;
use OCA\Tables\Db\UserArchiveMapper;
use OCA\Tables\Service\ArchiveCleanupService;
use OCA\Tables\Service\PermissionsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ArchiveCleanupServiceTest extends TestCase {
	private UserArchiveMapper&MockObject $userArchiveMapper;
	private ShareMapper&MockObject $shareMapper;
	private PermissionsService&MockObject $permissionsService;
	private ArchiveCleanupService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->userArchiveMapper = $this->createMock(UserArchiveMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->service = new ArchiveCleanupService(
			$this->userArchiveMapper,
			$this->shareMapper,
			$this->permissionsService,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testMembershipLossRemovesOverrideWithoutRemainingAccess(): void {
		$this->shareMapper->method('findNodesByReceiver')
			->with('team-a', ShareReceiverType::GROUP)
			->willReturn([['nodeType' => 'table', 'nodeId' => 5]]);
		$this->userArchiveMapper->method('findAllOverridesForUser')
			->with('alice', Application::NODE_TYPE_TABLE, [5])
			->willReturn([5 => new UserArchive()]);
		$this->permissionsService->method('canAccessNodeById')
			->with(Application::NODE_TYPE_TABLE, 5, 'alice')
			->willReturn(false);

		$this->userArchiveMapper->expects($this->once())
			->method('deleteForUser')
			->with('alice', Application::NODE_TYPE_TABLE, 5);

		$this->service->cleanupAfterMembershipLoss(['alice'], 'team-a', ShareReceiverType::GROUP);
	}

	public function testMembershipLossKeepsOverrideWithRemainingAccess(): void {
		$this->shareMapper->method('findNodesByReceiver')
			->willReturn([['nodeType' => 'table', 'nodeId' => 5]]);
		$this->userArchiveMapper->method('findAllOverridesForUser')
			->willReturn([5 => new UserArchive()]);
		$this->permissionsService->method('canAccessNodeById')
			->willReturn(true);

		$this->userArchiveMapper->expects($this->never())->method('deleteForUser');

		$this->service->cleanupAfterMembershipLoss(['alice'], 'team-a', ShareReceiverType::GROUP);
	}

	public function testMembershipLossChecksContextsViaContextPermission(): void {
		$this->shareMapper->method('findNodesByReceiver')
			->willReturn([['nodeType' => 'context', 'nodeId' => 7]]);
		$this->userArchiveMapper->method('findAllOverridesForUser')
			->with('alice', Application::NODE_TYPE_CONTEXT, [7])
			->willReturn([7 => new UserArchive()]);
		$this->permissionsService->method('canAccessContextById')
			->with(7, 'alice')
			->willReturn(false);

		$this->userArchiveMapper->expects($this->once())
			->method('deleteForUser')
			->with('alice', Application::NODE_TYPE_CONTEXT, 7);

		$this->service->cleanupAfterMembershipLoss(['alice'], 'team-a', ShareReceiverType::CIRCLE);
	}

	public function testMembershipLossSkipsNodesWithoutOverride(): void {
		$this->shareMapper->method('findNodesByReceiver')
			->willReturn([['nodeType' => 'table', 'nodeId' => 5]]);
		$this->userArchiveMapper->method('findAllOverridesForUser')
			->willReturn([]);

		$this->permissionsService->expects($this->never())->method('canAccessNodeById');
		$this->userArchiveMapper->expects($this->never())->method('deleteForUser');

		$this->service->cleanupAfterMembershipLoss(['alice'], 'team-a', ShareReceiverType::GROUP);
	}

	public function testMembershipLossIgnoresViewShares(): void {
		$this->shareMapper->method('findNodesByReceiver')
			->willReturn([['nodeType' => 'view', 'nodeId' => 9]]);

		$this->userArchiveMapper->expects($this->never())->method('findAllOverridesForUser');

		$this->service->cleanupAfterMembershipLoss(['alice'], 'team-a', ShareReceiverType::GROUP);
	}

	public function testPurgeNodeOverridesRemovesOnlyStaleHolders(): void {
		$this->userArchiveMapper->method('findUserIdsForNode')
			->with(Application::NODE_TYPE_TABLE, 5)
			->willReturn(['alice', 'bob']);
		$this->permissionsService->method('canAccessNodeById')
			->willReturnCallback(static fn (int $nodeType, int $nodeId, string $userId) => $userId === 'bob');

		$this->userArchiveMapper->expects($this->once())
			->method('deleteForUser')
			->with('alice', Application::NODE_TYPE_TABLE, 5);

		$this->service->purgeNodeOverrides(Application::NODE_TYPE_TABLE, 5);
	}

	public function testShareDeletionForUserShareChecksReceiverOnly(): void {
		$share = new Share();
		$share->setNodeType('table');
		$share->setNodeId(5);
		$share->setReceiver('alice');
		$share->setReceiverType(ShareReceiverType::USER);

		$this->permissionsService->method('canAccessNodeById')->willReturn(false);
		$this->userArchiveMapper->expects($this->once())
			->method('deleteForUser')
			->with('alice', Application::NODE_TYPE_TABLE, 5);
		$this->userArchiveMapper->expects($this->never())->method('findUserIdsForNode');

		$this->service->cleanupAfterShareDeletion($share);
	}

	public function testShareDeletionForGroupSharePurgesNode(): void {
		$share = new Share();
		$share->setNodeType('context');
		$share->setNodeId(7);
		$share->setReceiver('team-a');
		$share->setReceiverType(ShareReceiverType::GROUP);

		$this->userArchiveMapper->method('findUserIdsForNode')
			->with(Application::NODE_TYPE_CONTEXT, 7)
			->willReturn(['alice']);
		$this->permissionsService->method('canAccessContextById')->willReturn(false);

		$this->userArchiveMapper->expects($this->once())
			->method('deleteForUser')
			->with('alice', Application::NODE_TYPE_CONTEXT, 7);

		$this->service->cleanupAfterShareDeletion($share);
	}

	public function testShareDeletionIgnoresViewShares(): void {
		$share = new Share();
		$share->setNodeType('view');
		$share->setNodeId(9);
		$share->setReceiver('alice');
		$share->setReceiverType(ShareReceiverType::USER);

		$this->userArchiveMapper->expects($this->never())->method('deleteForUser');
		$this->userArchiveMapper->expects($this->never())->method('findUserIdsForNode');

		$this->service->cleanupAfterShareDeletion($share);
	}

	public function testCleanupDeletedUserDelegatesToMapper(): void {
		$this->userArchiveMapper->expects($this->once())
			->method('deleteAllForUser')
			->with('alice');

		$this->service->cleanupDeletedUser('alice');
	}
}
