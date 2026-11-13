<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Listener;

use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\ContextNavigation;
use OCA\Tables\Db\ContextNavigationMapper;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Listener\ReceiverCleanupListener;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IGroup;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * Runs against the database: deleting a user or group removes the shares made to them
 * and the application navigation settings that belong to those shares or to the user.
 */
class ReceiverCleanupListenerTest extends DatabaseTestCase {
	private ShareMapper $shareMapper;
	private ContextNavigationMapper $navigationMapper;
	private ReceiverCleanupListener $listener;

	protected function setUp(): void {
		parent::setUp();
		$this->shareMapper = new ShareMapper($this->createMock(LoggerInterface::class), $this->connectionAdapter);
		$this->navigationMapper = new ContextNavigationMapper($this->connectionAdapter);
		$this->listener = new ReceiverCleanupListener($this->shareMapper, $this->navigationMapper, $this->createMock(LoggerInterface::class));
	}

	protected function tearDown(): void {
		$this->cleanupTablesData();
		parent::tearDown();
	}

	public function testDeletingAUserRemovesTheNavigationOfTheirSharesAndTheirOwnNavigation(): void {
		$toGone = $this->share('gone', ShareReceiverType::USER);
		$toStays = $this->share('stays', ShareReceiverType::USER);
		$toGroup = $this->share('team', ShareReceiverType::GROUP);
		$this->navigation($toGone, '');
		$this->navigation($toGone, 'gone');
		$this->navigation($toGroup, 'gone');
		$this->navigation($toStays, '');
		$this->navigation($toGroup, 'stays');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('gone');
		$this->listener->handle(new UserDeletedEvent($user));

		$this->assertSame([[$toStays, ''], [$toGroup, 'stays']], $this->storedNavigation());
	}

	public function testDeletingAGroupRemovesTheNavigationOfItsShares(): void {
		$toGroup = $this->share('team', ShareReceiverType::GROUP);
		$toUser = $this->share('team', ShareReceiverType::USER);
		$this->navigation($toGroup, '');
		$this->navigation($toGroup, 'member');
		$this->navigation($toUser, '');

		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('team');
		$this->listener->handle(new GroupDeletedEvent($group));

		$this->assertSame([[$toUser, '']], $this->storedNavigation());
	}

	private function share(string $receiver, string $receiverType): int {
		$share = new Share();
		$share->setSender('owner');
		$share->setReceiver($receiver);
		$share->setReceiverType($receiverType);
		$share->setNodeId(990801);
		$share->setNodeType('context');
		$share->setPermissionRead(true);
		$share->setPermissionCreate(false);
		$share->setPermissionUpdate(false);
		$share->setPermissionDelete(false);
		$share->setPermissionManage(false);
		$share->setCreatedAt('2026-10-30 12:00:00');
		$share->setLastEditAt('2026-10-30 12:00:00');
		return $this->shareMapper->insert($share)->getId();
	}

	private function navigation(int $shareId, string $userId): void {
		$navigation = new ContextNavigation();
		$navigation->setShareId($shareId);
		$navigation->setUserId($userId);
		$navigation->setDisplayMode(1);
		$this->navigationMapper->insert($navigation);
	}

	/**
	 * @return list<array{int, string}> share id and user id of every stored navigation setting
	 */
	private function storedNavigation(): array {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$rows = $qb->select('share_id', 'user_id')
			->from('tables_contexts_navigation')
			->orderBy('share_id')
			->addOrderBy('user_id')
			->executeQuery()
			->fetchAllAssociative();
		return array_map(static fn (array $row): array => [(int)$row['share_id'], (string)$row['user_id']], $rows);
	}
}
