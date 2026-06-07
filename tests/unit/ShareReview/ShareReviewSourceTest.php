<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\ShareReview;

use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNavigationMapper;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\ShareReview\ShareReviewSource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\IL10N;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ShareReviewSourceTest extends TestCase {
	private MockObject $shareMapper;
	private MockObject $contextNavigationMapper;
	private MockObject $tableMapper;
	private MockObject $viewMapper;
	private MockObject $contextMapper;
	private MockObject $l10n;
	private MockObject $logger;
	private ShareReviewSource $source;

	protected function setUp(): void {
		parent::setUp();
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->contextNavigationMapper = $this->createMock(ContextNavigationMapper::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(fn (string $text, array $params = []) => vsprintf($text, $params));
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->source = new ShareReviewSource(
			$this->shareMapper,
			$this->contextNavigationMapper,
			$this->tableMapper,
			$this->viewMapper,
			$this->contextMapper,
			$this->l10n,
			$this->logger,
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

	private function makeShare(int $id, string $nodeType): Share {
		$share = new Share();
		$share->setId($id);
		$share->setNodeType($nodeType);
		return $share;
	}

	public function testDeleteShareTableSuccessSkipsContextNavCleanup(): void {
		$share = $this->makeShare(7, 'table');
		$this->shareMapper->expects($this->once())->method('find')->with(7)->willReturn($share);
		$this->shareMapper->expects($this->once())->method('delete')->with($share);
		$this->contextNavigationMapper->expects($this->never())->method('deleteByShareId');
		$this->logger->expects($this->once())->method('info');

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareContextSuccessCleansUpContextNav(): void {
		$share = $this->makeShare(7, 'context');
		$this->shareMapper->expects($this->once())->method('find')->with(7)->willReturn($share);
		$this->shareMapper->expects($this->once())->method('delete')->with($share);
		$this->contextNavigationMapper->expects($this->once())->method('deleteByShareId')->with(7);
		$this->logger->expects($this->once())->method('info');

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareNotFoundReturnsFalse(): void {
		$this->shareMapper->method('find')->willThrowException(new DoesNotExistException('not found'));
		$this->shareMapper->expects($this->never())->method('delete');
		$this->contextNavigationMapper->expects($this->never())->method('deleteByShareId');
		$this->logger->expects($this->once())->method('info');
		$this->logger->expects($this->once())->method('warning');

		$this->assertFalse($this->source->deleteShare('99'));
	}

	public function testDeleteShareReturnsFalseOnDeleteException(): void {
		$share = $this->makeShare(7, 'table');
		$this->shareMapper->method('find')->willReturn($share);
		$this->shareMapper->method('delete')->willThrowException($this->createMock(Exception::class));
		$this->contextNavigationMapper->expects($this->never())->method('deleteByShareId');
		$this->logger->expects($this->once())->method('info');
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareReturnsTrueOnContextNavigationException(): void {
		$share = $this->makeShare(42, 'context');
		$this->shareMapper->method('find')->willReturn($share);
		$this->shareMapper->method('delete');
		$this->contextNavigationMapper->method('deleteByShareId')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('info');
		$this->logger->expects($this->once())->method('error');

		$this->assertTrue($this->source->deleteShare('42'));
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
