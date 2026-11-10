<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Constants\UsergroupType;
use OCA\Tables\Db\Column;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Helper\CircleHelper;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UsergroupBusinessTest extends TestCase {
	private UsergroupBusiness $usergroupBusiness;

	protected function setUp(): void {
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturnCallback(
			fn (string $id): ?IUser => $id === 'alice' ? $this->createMock(IUser::class) : null,
		);
		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->willReturnCallback(
			fn (string $id): ?IGroup => $id === 'staff' ? $this->createMock(IGroup::class) : null,
		);
		$circleHelper = $this->createMock(CircleHelper::class);
		$circleHelper->method('circleExists')->willReturnCallback(
			fn (string $id): bool => $id === 'team1',
		);

		$this->usergroupBusiness = new UsergroupBusiness(
			$this->createMock(LoggerInterface::class),
			$circleHelper,
			$userManager,
			$groupManager,
		);
	}

	private function validate(array $value): void {
		$this->usergroupBusiness->validateValue($value, new Column(), 'alice', 1, null);
	}

	public function testAcceptsExistingUsersGroupsAndTeams(): void {
		$this->expectNotToPerformAssertions();

		$this->validate([
			['id' => 'alice', 'type' => UsergroupType::USER],
			['id' => 'staff', 'type' => UsergroupType::GROUP],
			['id' => 'team1', 'type' => UsergroupType::CIRCLE],
		]);
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function invalidTypeProvider(): array {
		return [
			'unknown type' => [3],
			'negative type' => [-1],
			'type as a string' => ['0'],
			'missing type' => [null],
		];
	}

	/**
	 * @dataProvider invalidTypeProvider
	 */
	public function testRejectsAnEntryOfAnUnknownType(mixed $type): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Invalid usergroup type');

		$this->validate([['id' => str_repeat('x', 3000), 'type' => $type]]);
	}

	public function testRejectsAnUnknownTypeNextToValidEntries(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Invalid usergroup type');

		$this->validate([
			['id' => 'alice', 'type' => UsergroupType::USER],
			['id' => 'anything', 'type' => 3],
		]);
	}

	public function testRejectsAUserThatDoesNotExist(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('User not found');

		$this->validate([['id' => 'mallory', 'type' => UsergroupType::USER]]);
	}
}
