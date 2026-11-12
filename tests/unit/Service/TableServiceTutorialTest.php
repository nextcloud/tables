<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
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
use OCA\Tables\Service\ViewService;
use OCA\Tables\Helper\UserHelper;
use OCP\App\IAppManager;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TableServiceTutorialTest extends TestCase {
	private PermissionsService&MockObject $permissionsService;
	private TableMapper&MockObject $mapper;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->mapper = $this->createMock(TableMapper::class);
	}

	/**
	 * findAll() resolves the user itself and then creates the tutorial table for whoever owns
	 * nothing yet. Leaving that call without the user makes create() ask the session instead,
	 * which has no user outside a request: the whole listing then throws before it reaches
	 * the shared tables, so an occ or background caller sees nothing for that account.
	 */
	public function testTheTutorialTableIsCreatedForTheUserTheListingWasAskedFor(): void {
		$this->permissionsService->method('preCheckUserId')->willReturnCallback(
			static fn (?string $userId, bool $canBeEmpty = true): string => (string)$userId,
		);
		$this->mapper->method('findAll')->with('alice')->willReturn([]);

		$service = $this->serviceMockingCreate();
		$service->expects($this->once())
			->method('create')
			->with($this->anything(), 'tutorial', $this->anything(), $this->anything(), 'alice')
			->willReturn($this->tableOwnedBy('alice'));

		$service->findAll('alice', skipTableEnhancement: true, skipSharedTables: true);
	}

	public function testNoTutorialTableIsCreatedWhenTheUserAlreadyOwnsOne(): void {
		$this->permissionsService->method('preCheckUserId')->willReturnCallback(
			static fn (?string $userId, bool $canBeEmpty = true): string => (string)$userId,
		);
		$this->mapper->method('findAll')->with('alice')->willReturn([$this->tableOwnedBy('alice')]);

		$service = $this->serviceMockingCreate();
		$service->expects($this->never())->method('create');

		$service->findAll('alice', skipTableEnhancement: true, skipSharedTables: true);
	}

	private function tableOwnedBy(string $userId): Table {
		$table = new Table();
		$table->setId(1);
		$table->setTitle('Welcome');
		$table->setOwnership($userId);

		return $table;
	}

	/**
	 * create() does its own persistence, so it is the one method replaced here; everything
	 * else runs for real.
	 */
	private function serviceMockingCreate(): TableService&MockObject {
		return $this->getMockBuilder(TableService::class)
			->setConstructorArgs([
				$this->permissionsService,
				$this->createMock(LoggerInterface::class),
				'alice',
				$this->mapper,
				$this->createMock(TableTemplateService::class),
				$this->createMock(ColumnService::class),
				$this->createMock(RowService::class),
				$this->createMock(ViewService::class),
				$this->createMock(ShareService::class),
				$this->createMock(UserHelper::class),
				$this->createMock(FavoritesService::class),
				$this->createMock(IEventDispatcher::class),
				$this->createMock(ContextService::class),
				$this->createMock(IAppManager::class),
				$this->createMock(IDBConnection::class),
				$this->createMock(IL10N::class),
				$this->createMock(Defaults::class),
				$this->createMock(ActivityManager::class),
				$this->createMock(FederationService::class),
				$this->createMock(AuditLogServiceInterface::class),
			])
			->onlyMethods(['create'])
			->getMock();
	}
}
