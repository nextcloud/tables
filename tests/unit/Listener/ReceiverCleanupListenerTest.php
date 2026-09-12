<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Listener;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Model\Circle;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\ContextNavigation;
use OCA\Tables\Db\ContextNavigationMapper;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Listener\ReceiverCleanupListener;
use OCA\Tables\Service\ArchiveCleanupService;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IGroup;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Runs against the database: deleting a user, group or team removes the shares made to them
 * and the application navigation settings that belong to those shares or to the user.
 */
class ReceiverCleanupListenerTest extends DatabaseTestCase {
	private ShareMapper $shareMapper;
	private ContextNavigationMapper $navigationMapper;
	private ArchiveCleanupService&MockObject $archiveCleanupService;
	private LoggerInterface&MockObject $logger;
	/** @var int[] */
	private array $shareIds = [];

	protected function setUp(): void {
		parent::setUp();
		$this->shareMapper = new ShareMapper($this->createMock(LoggerInterface::class), $this->connectionAdapter);
		$this->navigationMapper = new ContextNavigationMapper($this->connectionAdapter);
		$this->archiveCleanupService = $this->createMock(ArchiveCleanupService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	protected function tearDown(): void {
		if ($this->shareIds !== []) {
			foreach (['tables_contexts_navigation' => 'share_id', 'tables_shares' => 'id'] as $table => $column) {
				$qb = $this->connectionAdapter->getQueryBuilder();
				$qb->delete($table)
					->where($qb->expr()->in($column, $qb->createNamedParameter($this->shareIds, IQueryBuilder::PARAM_INT_ARRAY)))
					->executeStatement();
			}
		}
		parent::tearDown();
	}

	public function testDeletingAUserRemovesTheirSharesTheNavigationOfThemAndTheirOwnNavigation(): void {
		$toGone = $this->share('gone', ShareReceiverType::USER);
		$tableToGone = $this->share('gone', ShareReceiverType::USER, 'table');
		$toStays = $this->share('stays', ShareReceiverType::USER);
		$toGroup = $this->share('team', ShareReceiverType::GROUP);
		$this->navigation($toGone, '');
		$this->navigation($toGone, 'gone');
		$this->navigation($toGroup, 'gone');
		$this->navigation($toStays, '');
		$this->navigation($toGroup, 'stays');

		$this->logger->expects($this->never())->method('warning');
		$this->archiveCleanupService->expects($this->once())->method('cleanupDeletedUser')->with('gone');
		$this->listener()->handle(new UserDeletedEvent($this->user('gone')));

		$this->assertSame([$toStays, $toGroup], $this->storedShares());
		$this->assertSame([[$toStays, ''], [$toGroup, 'stays']], $this->storedNavigation());
	}

	public function testDeletingAGroupRemovesItsSharesAndTheirNavigation(): void {
		$toGroup = $this->share('team', ShareReceiverType::GROUP);
		$toUser = $this->share('team', ShareReceiverType::USER);
		$this->navigation($toGroup, '');
		$this->navigation($toGroup, 'member');
		$this->navigation($toUser, '');

		$this->logger->expects($this->never())->method('warning');
		$this->archiveCleanupService->expects($this->once())->method('purgeShareNodeOverrides')->with('context', 990801);
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')->willReturn('team');
		$this->listener()->handle(new GroupDeletedEvent($group));

		$this->assertSame([$toUser], $this->storedShares());
		$this->assertSame([[$toUser, '']], $this->storedNavigation());
	}

	public function testDeletingATeamRemovesItsSharesAndTheirNavigation(): void {
		if (!class_exists(CircleDestroyedEvent::class)) {
			$this->markTestSkipped('The circles app is not available');
		}
		$toTeam = $this->share('team-single-id', ShareReceiverType::CIRCLE);
		$toGroup = $this->share('team-single-id', ShareReceiverType::GROUP);
		$this->navigation($toTeam, '');
		$this->navigation($toTeam, 'member');
		$this->navigation($toGroup, '');

		$this->logger->expects($this->never())->method('warning');
		$this->archiveCleanupService->expects($this->once())->method('purgeShareNodeOverrides')->with('context', 990801);
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')->willReturn('team-single-id');
		$event = $this->createMock(CircleDestroyedEvent::class);
		$event->method('getCircle')->willReturn($circle);
		$this->listener()->handle($event);

		$this->assertSame([$toGroup], $this->storedShares());
		$this->assertSame([[$toGroup, '']], $this->storedNavigation());
	}

	public function testSharesAreDeletedWhenTheNavigationCleanupFails(): void {
		$toGone = $this->share('gone', ShareReceiverType::USER);
		$tableToGone = $this->share('gone', ShareReceiverType::USER, 'table');
		$toStays = $this->share('stays', ShareReceiverType::USER);

		$navigationMapper = $this->createMock(ContextNavigationMapper::class);
		$navigationMapper->expects($this->once())
			->method('deleteByShareIds')
			->with([$toGone])
			->willThrowException(new Exception('locked'));
		$navigationMapper->method('deleteByUserId')->willThrowException(new Exception('locked'));
		$this->logger->expects($this->exactly(2))->method('warning');

		$this->listener($navigationMapper)->handle(new UserDeletedEvent($this->user('gone')));

		$this->assertSame([$toStays], $this->storedShares());
	}

	private function listener(?ContextNavigationMapper $navigationMapper = null): ReceiverCleanupListener {
		return new ReceiverCleanupListener($this->shareMapper, $navigationMapper ?? $this->navigationMapper, $this->archiveCleanupService, $this->logger);
	}

	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}

	private function share(string $receiver, string $receiverType, string $nodeType = 'context'): int {
		$share = new Share();
		$share->setSender('owner');
		$share->setReceiver($receiver);
		$share->setReceiverType($receiverType);
		$share->setNodeId(990801);
		$share->setNodeType($nodeType);
		$share->setCreatedAt('2026-10-30 12:00:00');
		$share->setLastEditAt('2026-10-30 12:00:00');
		$id = $this->shareMapper->insert($share)->getId();
		$this->shareIds[] = $id;
		return $id;
	}

	private function navigation(int $shareId, string $userId): void {
		$navigation = new ContextNavigation();
		$navigation->setShareId($shareId);
		$navigation->setUserId($userId);
		$navigation->setDisplayMode(Application::NAV_ENTRY_MODE_RECIPIENTS);
		$this->navigationMapper->insert($navigation);
	}

	/**
	 * @return int[] ids of the fixture shares that are still stored
	 */
	private function storedShares(): array {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$result = $qb->select('id')
			->from('tables_shares')
			->where($qb->expr()->in('id', $qb->createNamedParameter($this->shareIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('id')
			->executeQuery();
		$ids = array_map(intval(...), array_column($result->fetchAll(), 'id'));
		$result->closeCursor();
		return $ids;
	}

	/**
	 * @return list<array{int, string}> share id and user id of the stored navigation settings of the fixture shares
	 */
	private function storedNavigation(): array {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$rows = $qb->select('share_id', 'user_id')
			->from('tables_contexts_navigation')
			->where($qb->expr()->in('share_id', $qb->createNamedParameter($this->shareIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('share_id')
			->addOrderBy('user_id')
			->executeQuery()
			->fetchAllAssociative();
		return array_map(static fn (array $row): array => [(int)$row['share_id'], (string)$row['user_id']], $rows);
	}
}
