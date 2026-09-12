<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\BackgroundJob\CleanupArchiveOverridesJob;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\UserArchive;
use OCA\Tables\Db\UserArchiveMapper;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Service\ArchiveCleanupService;
use OCA\Tables\Service\PermissionsService;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ArchiveCleanupServiceTest extends TestCase {
	private UserArchiveMapper&MockObject $userArchiveMapper;
	private ShareMapper&MockObject $shareMapper;
	private CircleHelper&MockObject $circleHelper;
	private IJobList&MockObject $jobList;
	private PermissionsService&MockObject $permissionsService;
	private ArchiveCleanupService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->userArchiveMapper = $this->createMock(UserArchiveMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->circleHelper = $this->createMock(CircleHelper::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->service = new ArchiveCleanupService(
			$this->userArchiveMapper,
			$this->shareMapper,
			$this->circleHelper,
			$this->permissionsService,
			$this->jobList,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testMembershipLossSchedulesCleanupForHoldersOfOverrides(): void {
		$this->shareMapper->method('findNodesByReceiver')
			->with('team-a', ShareReceiverType::GROUP)
			->willReturn([['nodeType' => 'table', 'nodeId' => 5]]);
		$this->userArchiveMapper->method('findAllOverridesForUser')
			->willReturn([5 => new UserArchive()]);

		$this->jobList->expects($this->once())
			->method('add')
			->with(CleanupArchiveOverridesJob::class, [
				'nodeType' => Application::NODE_TYPE_TABLE,
				'nodeId' => 5,
				'userIds' => ['alice'],
			]);
		$this->userArchiveMapper->expects($this->never())->method('deleteForUser');

		$this->service->cleanupAfterMembershipLoss(['alice'], 'team-a', ShareReceiverType::GROUP);
	}

	public function testRemoveOverrideIfStaleRemovesWithoutRemainingAccess(): void {
		$this->permissionsService->method('canAccessNodeById')
			->with(Application::NODE_TYPE_TABLE, 5, 'alice')
			->willReturn(false);

		$this->userArchiveMapper->expects($this->once())
			->method('deleteForUser')
			->with('alice', Application::NODE_TYPE_TABLE, 5);

		$this->service->removeOverrideIfStale('alice', Application::NODE_TYPE_TABLE, 5);
	}

	public function testRemoveOverrideIfStaleKeepsOverrideWithRemainingAccess(): void {
		$this->permissionsService->method('canAccessNodeById')
			->willReturn(true);

		$this->userArchiveMapper->expects($this->never())->method('deleteForUser');

		$this->service->removeOverrideIfStale('alice', Application::NODE_TYPE_TABLE, 5);
	}

	public function testRemoveOverrideIfStaleChecksContextsViaContextPermission(): void {
		$this->permissionsService->method('canAccessContextById')
			->with(7, 'alice')
			->willReturn(false);

		$this->userArchiveMapper->expects($this->once())
			->method('deleteForUser')
			->with('alice', Application::NODE_TYPE_CONTEXT, 7);

		$this->service->removeOverrideIfStale('alice', Application::NODE_TYPE_CONTEXT, 7);
	}

	public function testRemoveOverrideIfStaleKeepsOverrideReachableViaTeamShare(): void {
		$teamShare = new Share();
		$teamShare->setNodeType('context');
		$teamShare->setNodeId(7);
		$teamShare->setReceiver('team-b');
		$teamShare->setReceiverType(ShareReceiverType::CIRCLE);

		$this->permissionsService->method('canAccessContextById')->willReturn(false);
		$this->circleHelper->method('getCircleIdsForUser')->willReturn(['team-b']);
		$this->shareMapper->method('findAllSharesForNodeTo')->willReturn([$teamShare]);

		$this->userArchiveMapper->expects($this->never())->method('deleteForUser');

		$this->service->removeOverrideIfStale('alice', Application::NODE_TYPE_CONTEXT, 7);
	}

	public function testMembershipLossSkipsNodesWithoutOverride(): void {
		$this->shareMapper->method('findNodesByReceiver')
			->willReturn([['nodeType' => 'table', 'nodeId' => 5]]);
		$this->userArchiveMapper->method('findAllOverridesForUser')
			->willReturn([]);

		$this->jobList->expects($this->never())->method('add');
		$this->userArchiveMapper->expects($this->never())->method('deleteForUser');

		$this->service->cleanupAfterMembershipLoss(['alice'], 'team-a', ShareReceiverType::GROUP);
	}

	public function testMembershipLossIgnoresViewShares(): void {
		$this->shareMapper->method('findNodesByReceiver')
			->willReturn([['nodeType' => 'view', 'nodeId' => 9]]);

		$this->userArchiveMapper->expects($this->never())->method('findAllOverridesForUser');

		$this->service->cleanupAfterMembershipLoss(['alice'], 'team-a', ShareReceiverType::GROUP);
	}

	public function testPurgeNodeOverridesSchedulesTheHolders(): void {
		$this->userArchiveMapper->method('findUserIdsForNode')
			->with(Application::NODE_TYPE_TABLE, 5)
			->willReturn(['alice', 'bob']);

		$this->jobList->expects($this->once())
			->method('add')
			->with(CleanupArchiveOverridesJob::class, [
				'nodeType' => Application::NODE_TYPE_TABLE,
				'nodeId' => 5,
				'userIds' => ['alice', 'bob'],
			]);
		$this->permissionsService->expects($this->never())->method('canAccessNodeById');
		$this->userArchiveMapper->expects($this->never())->method('deleteForUser');

		$this->service->purgeNodeOverrides(Application::NODE_TYPE_TABLE, 5);
	}

	public function testPurgeShareNodeOverridesResolvesShareNodeType(): void {
		$this->userArchiveMapper->expects($this->once())
			->method('findUserIdsForNode')
			->with(Application::NODE_TYPE_TABLE, 5)
			->willReturn([]);
		$this->jobList->expects($this->never())->method('add');

		$this->service->purgeShareNodeOverrides('table', 5);
	}

	public function testPurgeShareNodeOverridesIgnoresViewShares(): void {
		$this->userArchiveMapper->expects($this->never())->method('findUserIdsForNode');

		$this->service->purgeShareNodeOverrides('view', 9);
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

		$this->jobList->expects($this->once())
			->method('add')
			->with(CleanupArchiveOverridesJob::class, [
				'nodeType' => Application::NODE_TYPE_CONTEXT,
				'nodeId' => 7,
				'userIds' => ['alice'],
			]);

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
