<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Listener;

use OCA\Circles\Events\CircleMemberRemovedEvent;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Listener\ArchiveCleanupListener;
use OCA\Tables\Service\ArchiveCleanupService;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IGroup;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ArchiveCleanupListenerTest extends TestCase {
	private ArchiveCleanupService&MockObject $cleanupService;
	private ArchiveCleanupListener $listener;

	protected function setUp(): void {
		parent::setUp();
		$this->cleanupService = $this->createMock(ArchiveCleanupService::class);
		$this->listener = new ArchiveCleanupListener(
			$this->cleanupService,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testUserRemovedFromGroupSchedulesGroupCleanup(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('bob');
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('team-a');
		$event = $this->createMock(UserRemovedEvent::class);
		$event->method('getUser')->willReturn($user);
		$event->method('getGroup')->willReturn($group);

		$this->cleanupService->expects($this->once())
			->method('cleanupAfterMembershipLoss')
			->with(['bob'], 'team-a', ShareReceiverType::GROUP);

		$this->listener->handle($event);
	}

	public function testCircleMemberRemovedDirectUserSchedulesCircleCleanup(): void {
		$member = $this->mockMember(Member::TYPE_USER, 'bob');
		$event = $this->mockCircleEvent($member, 'circle-single-id');

		$this->cleanupService->expects($this->once())
			->method('cleanupAfterMembershipLoss')
			->with(['bob'], 'circle-single-id', ShareReceiverType::CIRCLE);

		$this->listener->handle($event);
	}

	public function testCircleMemberRemovedSubCircleExpandsInheritedUsers(): void {
		$inherited = [
			$this->mockMember(Member::TYPE_USER, 'alice'),
			$this->mockMember(Member::TYPE_CIRCLE, ''), // nested non-user, must be skipped
			$this->mockMember(Member::TYPE_USER, 'bob'),
		];
		$basedOn = $this->createMock(Circle::class);
		$basedOn->method('getInheritedMembers')->willReturn($inherited);

		$member = $this->createMock(Member::class);
		$member->method('getUserType')->willReturn(Member::TYPE_CIRCLE);
		$member->method('getBasedOn')->willReturn($basedOn);
		$event = $this->mockCircleEvent($member, 'circle-single-id');

		$this->cleanupService->expects($this->once())
			->method('cleanupAfterMembershipLoss')
			->with(['alice', 'bob'], 'circle-single-id', ShareReceiverType::CIRCLE);

		$this->listener->handle($event);
	}

	public function testCircleMemberRemovedWithNullMemberDoesNothing(): void {
		$event = $this->mockCircleEvent(null, 'circle-single-id');

		$this->cleanupService->expects($this->never())->method('cleanupAfterMembershipLoss');

		$this->listener->handle($event);
	}

	public function testCircleMemberRemovedSubCircleWithNullBasedOnDoesNothing(): void {
		$member = $this->createMock(Member::class);
		$member->method('getUserType')->willReturn(Member::TYPE_CIRCLE);
		$member->method('getBasedOn')->willReturn(null);
		$event = $this->mockCircleEvent($member, 'circle-single-id');

		$this->cleanupService->expects($this->never())->method('cleanupAfterMembershipLoss');

		$this->listener->handle($event);
	}

	private function mockMember(int $type, string $userId): Member&MockObject {
		$member = $this->createMock(Member::class);
		$member->method('getUserType')->willReturn($type);
		$member->method('getUserId')->willReturn($userId);
		return $member;
	}

	private function mockCircleEvent(?Member $member, string $singleId): CircleMemberRemovedEvent&MockObject {
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')->willReturn($singleId);
		$event = $this->createMock(CircleMemberRemovedEvent::class);
		$event->method('getMember')->willReturn($member);
		$event->method('getCircle')->willReturn($circle);
		return $event;
	}
}
