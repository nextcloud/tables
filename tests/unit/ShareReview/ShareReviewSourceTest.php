<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\ShareReview;

use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Service\ShareService;
use OCA\Tables\ShareReview\ShareReviewSource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\Share\IShare;
use OCP\Share\ShareReview\Events\ShareReviewAccessCheckEvent;
use OCP\Share\ShareReview\IPaginatedShareReviewSource;
use OCP\Share\ShareReview\ShareReviewCounts;
use OCP\Share\ShareReview\ShareReviewEntry;
use OCP\Share\ShareReview\ShareReviewPermission;
use OCP\Share\ShareReview\ShareReviewQuery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ShareReviewSourceTest extends TestCase {
	private MockObject $shareMapper;
	private MockObject $logger;
	private MockObject $shareService;
	private MockObject $eventDispatcher;
	private ShareReviewSource $source;

	protected function setUp(): void {
		parent::setUp();
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, array $params = []) => vsprintf($text, $params));
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->shareService = $this->createMock(ShareService::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->source = new ShareReviewSource($this->shareMapper, $l10n, $this->logger, $this->shareService, $this->eventDispatcher);
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
			'node_name' => 'My Table',
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

	/** @param list<array<string, mixed>> $rows */
	private function stubPage(array $rows): void {
		$this->shareMapper->method('findPageForShareReview')->willReturn($rows);
		$this->shareMapper->method('countForShareReview')->willReturn(new ShareReviewCounts(count($rows), count($rows)));
	}

	private function firstEntry(array $row): ShareReviewEntry {
		$this->stubPage([$row]);
		return $this->source->queryShares(new ShareReviewQuery())->entries[0];
	}

	public function testGetNameIsStableWhileGetDisplayNameIsTranslated(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []) => $text === 'Tables' ? 'Tabellen' : vsprintf($text, $params));
		$source = new ShareReviewSource($this->shareMapper, $l10n, $this->logger, $this->shareService, $this->eventDispatcher);

		$this->assertInstanceOf(IPaginatedShareReviewSource::class, $source);
		$this->assertSame('Tables', $source->getName());
		$this->assertSame('Tabellen', $source->getDisplayName());
	}

	public function testUserShareOfATable(): void {
		$share = $this->firstEntry($this->makeShareRow());

		$this->assertSame('1', $share->id);
		$this->assertSame('My Table (Table)', $share->object);
		$this->assertSame('alice', $share->initiator);
		$this->assertSame(IShare::TYPE_USER, $share->type);
		$this->assertSame('bob', $share->recipient);
		$this->assertSame([ShareReviewSource::PERMISSION_READ], $this->permissionIds($share->permissions));
		$this->assertFalse($share->hasPassword);
		$this->assertSame(strtotime('2026-01-15 12:00:00'), $share->lastModifiedTimestamp);
		$this->assertSame('', $share->action);
	}

	public function testLinkShareUsesTheTokenAsRecipientAndCarriesThePasswordFlag(): void {
		$share = $this->firstEntry($this->makeShareRow(['receiver_type' => 'link', 'receiver' => '', 'token' => 'abc123', 'password' => 'hash']));

		$this->assertSame(IShare::TYPE_LINK, $share->type);
		$this->assertSame('abc123', $share->recipient);
		$this->assertTrue($share->hasPassword);
	}

	public function testViewContextAndDeletedNodesAreLabelled(): void {
		$this->assertSame('Open Tasks (View)', $this->firstEntry($this->makeShareRow(['node_type' => 'view', 'node_name' => 'Open Tasks']))->object);
		$this->assertSame('Project X (Application)', $this->contextObject());
	}

	private function contextObject(): string {
		$mapper = $this->createMock(ShareMapper::class);
		$mapper->method('findPageForShareReview')->willReturn([$this->makeShareRow(['node_type' => 'context', 'node_name' => 'Project X'])]);
		$mapper->method('countForShareReview')->willReturn(new ShareReviewCounts(1, 1));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, array $params = []) => vsprintf($text, $params));
		$source = new ShareReviewSource($mapper, $l10n, $this->logger, $this->shareService, $this->eventDispatcher);
		return $source->queryShares(new ShareReviewQuery())->entries[0]->object;
	}

	public function testDeletedNodeFallsBackToTheIdLabel(): void {
		$this->assertSame('Table 10 (Table)', $this->firstEntry($this->makeShareRow(['node_name' => null]))->object);
	}

	public function testUnknownNodeTypeLogsWarning(): void {
		$this->logger->expects($this->once())->method('warning');

		$this->assertSame('Unknown 10', $this->firstEntry($this->makeShareRow(['node_type' => 'other']))->object);
	}

	public function testUnknownReceiverTypeLogsWarningAndFallsBackToUser(): void {
		$this->logger->expects($this->once())->method('warning');

		$this->assertSame(IShare::TYPE_USER, $this->firstEntry($this->makeShareRow(['receiver_type' => 'martian']))->type);
	}

	public function testGroupCircleAndRemoteTypes(): void {
		$this->assertSame(IShare::TYPE_GROUP, $this->firstEntry($this->makeShareRow(['receiver_type' => 'group']))->type);
		$this->assertSame(IShare::TYPE_CIRCLE, $this->contextTypeOf('circle'));
		$this->assertSame(IShare::TYPE_REMOTE, $this->contextTypeOf('remote'));
	}

	private function contextTypeOf(string $receiverType): int {
		$mapper = $this->createMock(ShareMapper::class);
		$mapper->method('findPageForShareReview')->willReturn([$this->makeShareRow(['receiver_type' => $receiverType])]);
		$mapper->method('countForShareReview')->willReturn(new ShareReviewCounts(1, 1));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, array $params = []) => vsprintf($text, $params));
		return (new ShareReviewSource($mapper, $l10n, $this->logger, $this->shareService, $this->eventDispatcher))->queryShares(new ShareReviewQuery())->entries[0]->type;
	}

	public function testQuerySharesTranslatesTypesAndPermissionIdsToNativeFilters(): void {
		$query = new ShareReviewQuery(shareTypes: [IShare::TYPE_LINK, IShare::TYPE_EMAIL, IShare::TYPE_USER], permissionIds: [ShareReviewSource::PERMISSION_MANAGE, 'deck:read', ShareReviewSource::PERMISSION_READ]);
		$counts = new ShareReviewCounts(9, 2);
		$this->shareMapper->expects($this->once())
			->method('findPageForShareReview')
			->with($query, ['user', 'link'], ['permission_read', 'permission_manage'])
			->willReturn([$this->makeShareRow(['id' => 3])]);
		$this->shareMapper->expects($this->once())
			->method('countForShareReview')
			->with($query, ['user', 'link'], ['permission_read', 'permission_manage'])
			->willReturn($counts);

		$page = $this->source->queryShares($query);

		$this->assertSame($counts, $page->counts);
		$this->assertSame('3', $page->entries[0]->id);
	}

	public function testForeignTypesAndPermissionIdsMatchNothing(): void {
		$query = new ShareReviewQuery(shareTypes: [IShare::TYPE_EMAIL], permissionIds: ['deck:read']);
		$this->shareMapper->expects($this->once())->method('countForShareReview')->with($query, [], [])->willReturn(new ShareReviewCounts(4, 0));

		$this->assertSame(0, $this->source->countShares($query)->filteredCount);
	}

	public function testGetSharesStreamsTheFullIdOrderedList(): void {
		$rows = array_map(fn (int $id) => $this->makeShareRow(['id' => $id]), range(1, ShareReviewQuery::MAX_LIMIT + 1));
		$this->shareMapper->expects($this->once())
			->method('findAllForShareReview')
			->willReturnCallback(static function () use ($rows): \Generator {
				yield from $rows;
			});

		$this->assertCount(ShareReviewQuery::MAX_LIMIT + 1, $this->source->getShares());
	}

	public function testDbExceptionsDegradeToEmptyResults(): void {
		$this->shareMapper->method('findAllForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->shareMapper->method('findPageForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->shareMapper->method('countForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->shareMapper->method('countByTypeForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->exactly(4))->method('error');

		$this->assertSame([], $this->source->getShares());
		$this->assertSame([], $this->source->queryShares(new ShareReviewQuery())->entries);
		$this->assertSame(0, $this->source->countShares(new ShareReviewQuery())->totalCount);
		$this->assertSame([], $this->source->countSharesByType(new ShareReviewQuery()));
	}

	public function testCountSharesByTypeMapsReceiverTypesAndSkipsUnknownOnes(): void {
		$this->shareMapper->method('countByTypeForShareReview')->willReturn(['user' => 3, 'link' => 1, 'remote' => 4, 'martian' => 2]);

		// unknown native types are excluded from the shareTypes filter, so
		// the counts must exclude them too
		$this->assertSame([IShare::TYPE_USER => 3, IShare::TYPE_LINK => 1, IShare::TYPE_REMOTE => 4], $this->source->countSharesByType(new ShareReviewQuery()));
	}

	public function testGetShareIsAKeyedLookup(): void {
		$this->shareMapper->expects($this->never())->method('findPageForShareReview');
		$this->shareMapper->expects($this->once())->method('findForShareReview')->with(7)
			->willReturn($this->makeShareRow(['id' => 7, 'node_type' => 'view', 'node_name' => 'Open Tasks', 'receiver' => 'carol']));

		$entry = $this->source->getShare('7');

		$this->assertNotNull($entry);
		$this->assertSame('7', $entry->id);
		$this->assertSame('Open Tasks (View)', $entry->object);
		$this->assertSame('carol', $entry->recipient);
	}

	public function testGetShareUnknownOrInvalidIdReturnsNull(): void {
		$this->shareMapper->method('findForShareReview')->willReturn(null);

		$this->assertNull($this->source->getShare('7'));
		$this->assertNull($this->source->getShare('abc'));
		$this->assertNull($this->source->getShare('1e3'));
	}

	public function testPermissionsAllFlagsFalseFallsBackToRead(): void {
		$share = $this->firstEntry($this->makeShareRow(['permission_read' => 0]));

		$this->assertSame([ShareReviewSource::PERMISSION_READ], $this->permissionIds($share->permissions));
	}

	public function testPermissionsManageOnlyEmitsManageWithReadFallback(): void {
		$share = $this->firstEntry($this->makeShareRow(['permission_read' => 0, 'permission_manage' => 1]));

		$this->assertSame([ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_MANAGE], $this->permissionIds($share->permissions));
	}

	public function testPermissionsAllFlagsTrueEmitsFullSet(): void {
		$share = $this->firstEntry($this->makeShareRow(['permission_create' => 1, 'permission_update' => 1, 'permission_delete' => 1, 'permission_manage' => 1]));

		$this->assertSame([
			ShareReviewSource::PERMISSION_READ,
			ShareReviewSource::PERMISSION_UPDATE,
			ShareReviewSource::PERMISSION_CREATE,
			ShareReviewSource::PERMISSION_DELETE,
			ShareReviewSource::PERMISSION_MANAGE,
		], $this->permissionIds($share->permissions));
		$this->assertSame('Administer the shared table and its sharing', $share->permissions[4]->hint);
	}

	public function testPermissionIdentifiers(): void {
		$this->assertSame('tables:read', ShareReviewSource::PERMISSION_READ);
		$this->assertSame('tables:update', ShareReviewSource::PERMISSION_UPDATE);
		$this->assertSame('tables:create', ShareReviewSource::PERMISSION_CREATE);
		$this->assertSame('tables:delete', ShareReviewSource::PERMISSION_DELETE);
		$this->assertSame('tables:manage', ShareReviewSource::PERMISSION_MANAGE);
	}

	/**
	 * @param list<ShareReviewPermission> $permissions
	 * @return list<string>
	 */
	private function permissionIds(array $permissions): array {
		return array_map(static fn (ShareReviewPermission $permission): string => $permission->id, $permissions);
	}

	public function testDeleteShareNonNumericReturnsFalse(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->assertFalse($this->source->deleteShare('abc'));
	}

	public function testDeleteShareEventNotHandledReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())->method('dispatchTyped')->with($this->isInstanceOf(ShareReviewAccessCheckEvent::class));
		$this->shareService->expects($this->never())->method('deleteForShareReview');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareEventDeniedReturnsFalse(): void {
		$this->eventDispatcher->method('dispatchTyped')->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
			$event->denyAccess('not in group');
		});
		$this->shareService->expects($this->never())->method('deleteForShareReview');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareEventGrantedReturnsTrue(): void {
		$this->eventDispatcher->method('dispatchTyped')->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
			$event->grantAccess();
		});
		$this->shareService->expects($this->once())->method('deleteForShareReview')->with(7);

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareDoesNotExistReturnsFalse(): void {
		$this->eventDispatcher->method('dispatchTyped')->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
			$event->grantAccess();
		});
		$this->shareService->method('deleteForShareReview')->willThrowException(new DoesNotExistException('gone'));

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareDbExceptionReturnsFalse(): void {
		$this->eventDispatcher->method('dispatchTyped')->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
			$event->grantAccess();
		});
		$this->shareService->method('deleteForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($this->source->deleteShare('7'));
	}
}
