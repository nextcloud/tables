<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Listener;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Tables\Listener\TeamOwnershipListener;
use OCA\Tables\Service\TableService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TeamOwnershipListenerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		if (!class_exists(RemovingCircleMemberEvent::class)) {
			self::markTestSkipped('The Circles app is required for these tests');
		}
	}

	/**
	 * @dataProvider removedMemberProvider
	 */
	public function testOnlyLocalUserDepartureTriggersHandover(string $type, bool $local, bool $expected): void {
		$member = $this->getMockBuilder(Member::class)->onlyMethods(['isLocal'])->getMock();
		$member->setUserType($type === 'user' ? Member::TYPE_USER : Member::TYPE_GROUP)->setUserId('alice');
		$member->method('isLocal')->willReturn($local);
		$event = $this->createMock(RemovingCircleMemberEvent::class);
		$event->method('getCircle')->willReturn((new Circle())->setSingleId('team'));
		$event->method('getMember')->willReturn($member);
		$service = $this->createMock(TableService::class);
		$service->expects($expected ? self::once() : self::never())->method('handleTeamStewardDeparture')->with('team', 'alice');

		(new TeamOwnershipListener($service, $this->createMock(LoggerInterface::class)))->handle($event);
	}

	public static function removedMemberProvider(): array {
		return [
			'local user' => ['user', true, true],
			'remote user' => ['user', false, false],
			'group' => ['group', true, false],
		];
	}

	public function testDestroyedTeamDeletesItsTables(): void {
		$event = $this->createMock(CircleDestroyedEvent::class);
		$event->method('getCircle')->willReturn((new Circle())->setSingleId('team'));
		$service = $this->createMock(TableService::class);
		$service->expects(self::once())->method('deleteTeamOwnedTables')->with('team');

		(new TeamOwnershipListener($service, $this->createMock(LoggerInterface::class)))->handle($event);
	}
}
