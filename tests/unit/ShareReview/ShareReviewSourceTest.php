<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\ShareReview;

use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ShareService;
use OCP\Share\Events\ShareReviewAccessCheckEvent;
use OCA\Tables\ShareReview\ShareReviewSource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ShareReviewSourceTest extends TestCase {
	private MockObject $shareMapper;
	private MockObject $tableMapper;
	private MockObject $viewMapper;
	private MockObject $contextMapper;
	private MockObject $l10n;
	private MockObject $logger;
	private MockObject $shareService;
	private MockObject $eventDispatcher;
	private ShareReviewSource $source;

	protected function setUp(): void {
		parent::setUp();
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(fn (string $text, array $params = []) => vsprintf($text, $params));
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->shareService = $this->createMock(ShareService::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->source = new ShareReviewSource(
			$this->shareMapper,
			$this->tableMapper,
			$this->viewMapper,
			$this->contextMapper,
			$this->l10n,
			$this->logger,
			$this->shareService,
			$this->eventDispatcher,
		);
	}

	/** @param array<string, mixed> $overrides */
	private function makeShareRow(array $overrides = []): array {
		return array_merge([
			'id' => 1,
			'sender' => 'alice',
			'receiver' => 'bob',
			'receiver_type' => 'user',
			'node_id' => 10,
			'node_type' => 'table',
			'token' => null,
			'password' => null,
			'permission_read' => 1,
			'permission_create' => 0,
			'permission_update' => 0,
			'permission_delete' => 0,
			'permission_manage' => 0,
			'created_at' => '2026-01-15 12:00:00',
			'last_edit_at' => '2026-01-15 12:00:00',
		], $overrides);
	}

	private function stubNameMappers(array $tableNames = [], array $viewNames = [], array $contextNames = []): void {
		$this->tableMapper->method('findIdToTitleMap')->willReturn($tableNames);
		$this->viewMapper->method('findIdToTitleMap')->willReturn($viewNames);
		$this->contextMapper->method('findIdToNameMap')->willReturn($contextNames);
	}

	public function testGetSharesEmpty(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([]);
		$this->stubNameMappers();

		$this->assertSame([], $this->source->getShares());
	}

	public function testGetSharesUserShare(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([$this->makeShareRow()]);
		$this->stubNameMappers(tableNames: [10 => 'My Table']);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$share = $shares[0];
		$this->assertSame(1, $share['id']);
		$this->assertSame('My Table (Table)', $share['object']);
		$this->assertSame('alice', $share['initiator']);
		$this->assertSame(IShare::TYPE_USER, $share['type']);
		$this->assertSame('bob', $share['recipient']);
		$this->assertSame(Constants::PERMISSION_READ, $share['permissions']);
		$this->assertFalse($share['password']);
		$this->assertSame('2026-01-15 12:00:00', $share['time']);
		$this->assertSame('', $share['action']);
	}

	public function testShareTimeUsesLastEditWhenNewer(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([
			$this->makeShareRow(['created_at' => '2026-01-15 12:00:00', 'last_edit_at' => '2026-06-01 08:00:00']),
		]);
		$this->stubNameMappers(tableNames: [10 => 'T']);

		$this->assertSame('2026-06-01 08:00:00', $this->source->getShares()[0]['time']);
	}

	public function testShareTimeUsesCreatedAtWhenLastEditEquals(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([
			$this->makeShareRow(['created_at' => '2026-01-15 12:00:00', 'last_edit_at' => '2026-01-15 12:00:00']),
		]);
		$this->stubNameMappers(tableNames: [10 => 'T']);

		$this->assertSame('2026-01-15 12:00:00', $this->source->getShares()[0]['time']);
	}

	public function testShareTimeUsesCreatedAtWhenLastEditIsNull(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([
			$this->makeShareRow(['created_at' => '2026-01-15 12:00:00', 'last_edit_at' => null]),
		]);
		$this->stubNameMappers(tableNames: [10 => 'T']);

		$this->assertSame('2026-01-15 12:00:00', $this->source->getShares()[0]['time']);
	}

	public function testGetSharesLinkShare(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([
			$this->makeShareRow(['receiver_type' => 'link', 'token' => 'abc123token', 'password' => 'hashed_pw']),
		]);
		$this->stubNameMappers(tableNames: [10 => 'Shared Table']);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame(IShare::TYPE_LINK, $shares[0]['type']);
		$this->assertSame('abc123token', $shares[0]['recipient']);
		$this->assertTrue($shares[0]['password']);
	}

	public function testGetSharesContextShare(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([
			$this->makeShareRow(['node_type' => 'context', 'node_id' => 99]),
		]);
		$this->stubNameMappers(contextNames: [99 => 'My Context']);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame('My Context (Application)', $shares[0]['object']);
	}

	public function testGetSharesMixedNodeTypes(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([
			$this->makeShareRow(['id' => 1, 'node_type' => 'table', 'node_id' => 10]),
			$this->makeShareRow(['id' => 2, 'node_type' => 'view', 'node_id' => 20]),
			$this->makeShareRow(['id' => 3, 'node_type' => 'context', 'node_id' => 30]),
		]);
		$this->stubNameMappers(
			tableNames: [10 => 'Table One'],
			viewNames: [20 => 'View One'],
			contextNames: [30 => 'Context One'],
		);

		$shares = $this->source->getShares();

		$this->assertCount(3, $shares);
		$this->assertSame('Table One (Table)', $shares[0]['object']);
		$this->assertSame('View One (View)', $shares[1]['object']);
		$this->assertSame('Context One (Application)', $shares[2]['object']);
	}

	public function testGetSharesDeletedNode(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([
			$this->makeShareRow(['node_type' => 'table', 'node_id' => 42]),
		]);
		$this->stubNameMappers();

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame('Table 42 (Table)', $shares[0]['object']);
	}

	public function testGetSharesUnknownNodeTypeLogsWarning(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([
			$this->makeShareRow(['node_type' => 'dashboard', 'node_id' => 5]),
		]);
		$this->stubNameMappers();
		$this->logger->expects($this->once())->method('warning');

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame('Unknown 5', $shares[0]['object']);
	}

	public function testGetSharesUnknownReceiverTypeLogsWarning(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([
			$this->makeShareRow(['receiver_type' => 'email', 'receiver' => 'bob@example.com']),
		]);
		$this->stubNameMappers(tableNames: [10 => 'My Table']);
		$this->logger->expects($this->once())->method('warning');

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$this->assertSame(IShare::TYPE_USER, $shares[0]['type']);
	}

	public function testGetSharesReturnsEmptyOnDbException(): void {
		$this->shareMapper->method('findAllRaw')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertSame([], $this->source->getShares());
	}

	public function testDeleteShareNonNumericReturnsFalse(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->assertFalse($this->source->deleteShare('abc'));
	}

	public function testDeleteShareEventNotHandledReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(ShareReviewAccessCheckEvent::class));
		$this->shareService->expects($this->never())->method('deleteForShareReview');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareEventDeniedReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(ShareReviewAccessCheckEvent::class))
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->denyAccess('not a share-review operator');
			});
		$this->shareService->expects($this->never())->method('deleteForShareReview');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareEventGrantedReturnsTrue(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(ShareReviewAccessCheckEvent::class))
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->shareService->expects($this->once())->method('deleteForShareReview')->with(7);

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareDoesNotExistReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->shareService->expects($this->once())
			->method('deleteForShareReview')
			->willThrowException($this->createMock(DoesNotExistException::class));

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareDbExceptionReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->shareService->expects($this->once())
			->method('deleteForShareReview')
			->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testComputePermissionsAllFalse(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([$this->makeShareRow([
			'permission_read' => 0,
			'permission_create' => 0,
			'permission_update' => 0,
			'permission_delete' => 0,
			'permission_manage' => 0,
		])]);
		$this->stubNameMappers(tableNames: [10 => 'T']);

		$this->assertSame(Constants::PERMISSION_READ, $this->source->getShares()[0]['permissions']);
	}

	public function testComputePermissionsAllTrue(): void {
		$this->shareMapper->method('findAllRaw')->willReturn([$this->makeShareRow([
			'permission_read' => 1,
			'permission_create' => 1,
			'permission_update' => 1,
			'permission_delete' => 1,
			'permission_manage' => 1,
		])]);
		$this->stubNameMappers(tableNames: [10 => 'T']);

		$expected = Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE | Constants::PERMISSION_DELETE | Constants::PERMISSION_SHARE;
		$this->assertSame($expected, $this->source->getShares()[0]['permissions']);
	}
}
