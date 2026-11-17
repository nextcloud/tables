<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Event\TableOwnershipTransferredEvent;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\FavoritesService;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\TableTemplateService;
use OCA\Tables\Service\ValueObject\ShareCreate;
use OCA\Tables\Service\ViewService;
use OCP\App\IAppManager;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TableServiceAuditLogTest extends TestCase {
	private PermissionsService&MockObject $permissionsService;
	private TableMapper&MockObject $mapper;
	private AuditLogServiceInterface&MockObject $auditLogService;
	private CircleHelper&MockObject $circleHelper;
	private ShareService&MockObject $shareService;
	private IDBConnection&MockObject $connection;
	private IEventDispatcher&MockObject $eventDispatcher;
	private TableService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->permissionsService->method('preCheckUserId')->willReturn('alice');
		$this->mapper = $this->createMock(TableMapper::class);
		$this->auditLogService = $this->createMock(AuditLogServiceInterface::class);
		$this->circleHelper = $this->createMock(CircleHelper::class);
		$this->shareService = $this->createMock(ShareService::class);
		$this->connection = $this->createMock(IDBConnection::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);

		$this->service = new TableService(
			$this->permissionsService,
			$this->createMock(LoggerInterface::class),
			'alice',
			$this->mapper,
			$this->createMock(TableTemplateService::class),
			$this->createMock(ColumnService::class),
			$this->createMock(RowService::class),
			$this->createMock(ViewService::class),
			$this->shareService,
			$this->createMock(UserHelper::class),
			$this->circleHelper,
			$this->createMock(FavoritesService::class),
			$this->eventDispatcher,
			$this->createMock(ContextService::class),
			$this->createMock(IAppManager::class),
			$this->connection,
			$this->createMock(IL10N::class),
			$this->createMock(Defaults::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(FederationService::class),
			$this->auditLogService,
		);
	}

	private function createTable(bool $archived): Table {
		$table = new Table();
		$table->setId(5);
		$table->setTitle('Budget');
		$table->setOwnership('alice');
		$table->setArchived($archived);
		return $table;
	}

	public function testCreateLogsCriticalAction(): void {
		$this->mapper->method('insert')->willReturnCallback(function (Table $table): Table {
			$table->setId(5);
			return $table;
		});
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::TableCreated, 'Table with ID: %s was created by user with ID: %s', [
				'tableId' => 5,
				'owner' => 'alice',
				'template' => 'custom',
			]);

		$this->service->create('Budget', 'custom', null, '', 'alice');
	}

	public function testUpdateLogsArchiving(): void {
		$this->mapper->method('find')->with(5)->willReturn($this->createTable(false));
		$this->mapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canUpdateTable')->willReturn(true);
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::TableArchived, 'Table with ID: %s was archived', ['tableId' => 5]);

		$this->service->update(5, null, null, null, true, 'alice');
	}

	public function testAnyTeamMemberCanCreateATeamTableWithAnOrdinaryRowShare(): void {
		$this->circleHelper->method('isCirclesEnabled')->willReturn(true);
		$this->circleHelper->method('circleExists')->with('team', 'alice')->willReturn(true);
		$this->mapper->method('insert')->willReturnCallback(static function (Table $table): Table {
			$table->setId(5);
			return $table;
		});
		$this->shareService->expects(self::once())->method('create')->with(self::callback(
			static fn (ShareCreate $share): bool => $share->getNodeId() === 5
				&& $share->getReceiver() === 'team'
				&& $share->getReceiverType() === 'circle'
				&& $share->getPermissionRead() && $share->getPermissionCreate()
				&& $share->getPermissionUpdate() && $share->getPermissionDelete()
				&& !$share->getPermissionManage(),
		));
		$this->connection->expects(self::once())->method('commit');

		$table = $this->service->create('Budget', 'custom', null, teamId: 'team');

		self::assertSame('team', $table->getTeamId());
		self::assertSame('alice', $table->getOwnership());
	}

	public function testNonMemberCannotCreateATeamTable(): void {
		$this->circleHelper->method('isCirclesEnabled')->willReturn(true);
		$this->circleHelper->method('circleExists')->willReturn(false);
		$this->mapper->expects(self::never())->method('insert');

		$this->expectException(PermissionError::class);
		$this->service->create('Budget', 'custom', null, teamId: 'team');
	}

	public function testTeamShareFailureRollsBackCreation(): void {
		$this->circleHelper->method('isCirclesEnabled')->willReturn(true);
		$this->circleHelper->method('circleExists')->willReturn(true);
		$this->mapper->method('insert')->willReturn($this->createTable(false));
		$this->shareService->method('create')->willThrowException(new InternalError('share failed'));
		$this->connection->expects(self::once())->method('rollBack');
		$this->connection->expects(self::never())->method('commit');

		$this->expectException(InternalError::class);
		$this->service->create('Budget', 'custom', null, teamId: 'team');
	}

	public function testOwnerDepartureTransfersTheUserOwnerWithoutDetachingTheTeam(): void {
		$table = $this->createTable(false);
		$table->setTeamId('team');
		$this->mapper->method('findAllByTeamId')->with('team', 'alice')->willReturn([$table]);
		$this->mapper->method('findForUpdate')->with(5)->willReturn($table);
		$this->mapper->method('update')->willReturnArgument(0);
		$this->circleHelper->method('findSuccessorUserId')->with('team', 'alice')->willReturn('bob');
		$this->permissionsService->method('canChangeElementOwner')->with($table, 'alice')->willReturn(true);
		$this->shareService->expects(self::once())->method('changeSenderForNode')->with('table', 5, 'bob', 'alice');
		$this->eventDispatcher->expects(self::once())->method('dispatchTyped')->with(self::callback(
			static fn (TableOwnershipTransferredEvent $event): bool => $event->getFromUserId() === 'alice'
				&& $event->getToUserId() === 'bob',
		));

		$this->service->handleTeamStewardDeparture('team', 'alice');

		self::assertSame('team', $table->getTeamId());
		self::assertSame('bob', $table->getOwnership());
	}

	public function testFailedSuccessorLookupDoesNotDeleteTables(): void {
		$table = $this->createTable(false);
		$table->setTeamId('team');
		$this->mapper->method('findAllByTeamId')->willReturn([$table]);
		$this->circleHelper->method('findSuccessorUserId')->willThrowException(new InternalError('lookup failed'));
		$this->mapper->expects(self::never())->method('delete');

		$this->expectException(InternalError::class);
		$this->service->handleTeamStewardDeparture('team', 'alice');
	}

	/**
	 * @dataProvider teamDeletionProvider
	 */
	public function testTeamCleanupDeletesNormallyWithoutChangingOwnershipMetadata(bool $teamDeleted): void {
		$table = $this->createTable(false);
		$table->setTeamId('team');
		$this->mapper->method('findAllByTeamId')->willReturn([$table]);
		$this->mapper->method('findForUpdate')->with(5)->willReturn($table);
		$this->mapper->method('find')->with(5)->willReturn($table);
		$this->permissionsService->method('canManageTable')->with($table, 'alice')->willReturn(true);
		$this->mapper->expects(self::never())->method('update');
		$this->mapper->expects(self::once())->method('delete')->with($table)->willReturnArgument(0);
		$this->circleHelper->expects($teamDeleted ? self::never() : self::once())->method('findSuccessorUserId')->willReturn(null);

		if ($teamDeleted) {
			$this->service->deleteTeamOwnedTables('team');
		} else {
			$this->service->handleTeamStewardDeparture('team', 'alice');
		}

		self::assertSame('team', $table->getTeamId());
	}

	public static function teamDeletionProvider(): array {
		return [
			'team deleted' => [true],
			'last local member left' => [false],
		];
	}

	public function testUpdateLogsUnarchiving(): void {
		$this->mapper->method('find')->with(5)->willReturn($this->createTable(true));
		$this->mapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canUpdateTable')->willReturn(true);
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::TableUnarchived, 'Table with ID: %s was unarchived', ['tableId' => 5]);

		$this->service->update(5, null, null, null, false, 'alice');
	}

	public function testUpdateWithoutArchiveChangeDoesNotLog(): void {
		$this->mapper->method('find')->with(5)->willReturn($this->createTable(false));
		$this->mapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canUpdateTable')->willReturn(true);
		$this->auditLogService->expects($this->never())->method('log');

		$this->service->update(5, 'New title', null, null, false, 'alice');
	}
}
