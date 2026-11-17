<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Helper;

use OCA\Circles\Db\CircleRequest;
use OCA\Circles\Db\MemberRequest;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Helper\CircleHelper;
use OCP\App\IAppManager;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

final class CircleHelperTest extends TestCase {
	private CircleHelper $helper;
	private MemberRequest&MockObject $memberRequest;
	private Circle $circle;
	private CircleRequest&MockObject $circleRequest;

	protected function setUp(): void {
		parent::setUp();
		if (!class_exists(MemberRequest::class)) {
			self::markTestSkipped('The Circles app is required for these tests');
		}
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->willReturn(false);
		$this->helper = new CircleHelper($this->createMock(LoggerInterface::class), $appManager, $this->createMock(IL10N::class));
		$this->memberRequest = $this->createMock(MemberRequest::class);
		$this->circle = new Circle();
		$this->circleRequest = $this->createMock(CircleRequest::class);
		$this->circleRequest->method('getCircle')->with('team')->willReturn($this->circle);
		(new ReflectionProperty(CircleHelper::class, 'circlesEnabled'))->setValue($this->helper, true);
		(new ReflectionProperty(CircleHelper::class, 'memberRequest'))->setValue($this->helper, $this->memberRequest);
		(new ReflectionProperty(CircleHelper::class, 'circleRequest'))->setValue($this->helper, $this->circleRequest);
	}

	public function testUsesTheTeamOwnerWithoutListingMembers(): void {
		$this->circle->setOwner($this->member('owner', Member::LEVEL_OWNER));
		$this->memberRequest->expects(self::never())->method('getMembers');

		self::assertSame('owner', $this->helper->findSuccessorUserId('team', 'alice'));
	}

	/**
	 * @dataProvider ineligibleOwnerProvider
	 */
	public function testFallsBackWhenTheTeamOwnerIsIneligible(string $userId, bool $local, string $status): void {
		$this->circle->setOwner($this->member($userId, Member::LEVEL_OWNER, local: $local, status: $status));
		$this->memberRequest->expects(self::once())->method('getMembers')->willReturn([
			$this->member('bob', Member::LEVEL_MEMBER),
			$this->member('carol', Member::LEVEL_ADMIN),
		]);

		self::assertSame('carol', $this->helper->findSuccessorUserId('team', 'alice'));
	}

	public static function ineligibleOwnerProvider(): array {
		return [
			'departing owner' => ['alice', true, 'Member'],
			'remote owner' => ['owner', false, 'Member'],
			'inactive owner' => ['owner', true, 'Invited'],
			'empty user ID' => ['', true, 'Member'],
		];
	}

	public function testOwnerLookupFailureDoesNotFallBackOrLookLikeAnEmptyTeam(): void {
		$this->circleRequest->method('getCircle')->willThrowException(new \RuntimeException('unavailable'));
		$this->memberRequest->expects(self::never())->method('getMembers');

		$this->expectException(InternalError::class);
		$this->helper->findSuccessorUserId('team', 'alice');
	}

	public function testSelectsTheHighestRankedActiveLocalSuccessor(): void {
		$this->memberRequest->method('getMembers')->with('team')->willReturn([
			$this->member('alice', 9),
			$this->member('bob', 1),
			$this->member('carol', 8),
			$this->member('remote', 9, local: false),
			$this->member('invited', 9, status: Member::STATUS_INVITED),
			$this->member('group', 9, type: Member::TYPE_GROUP),
		]);

		self::assertSame('carol', $this->helper->findSuccessorUserId('team', 'alice'));
	}

	public function testNoRemainingEligibleMemberReturnsNull(): void {
		$this->memberRequest->method('getMembers')->willReturn([$this->member('alice', 9)]);

		self::assertNull($this->helper->findSuccessorUserId('team', 'alice'));
	}

	public function testLookupFailureDoesNotLookLikeAnEmptyTeam(): void {
		$this->memberRequest->method('getMembers')->willThrowException(new \RuntimeException('unavailable'));

		$this->expectException(InternalError::class);
		$this->helper->findSuccessorUserId('team', 'alice');
	}

	public function testDisabledCirclesDoesNotLookLikeAnEmptyTeam(): void {
		(new ReflectionProperty(CircleHelper::class, 'circlesEnabled'))->setValue($this->helper, false);

		$this->expectException(InternalError::class);
		$this->helper->findSuccessorUserId('team', 'alice');
	}

	private function member(string $userId, int $level, bool $local = true, string $status = Member::STATUS_MEMBER, int $type = Member::TYPE_USER): Member {
		$member = $this->getMockBuilder(Member::class)->onlyMethods(['isLocal'])->getMock();
		$member->method('isLocal')->willReturn($local);
		return $member->setUserId($userId)->setLevel($level)->setStatus($status)->setUserType($type);
	}
}
