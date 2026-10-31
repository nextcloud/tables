<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Db\FormattingRuleColMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\FilterSet;
use OCA\Tables\Model\ViewUpdateInput;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\FavoritesService;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\FormattingService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\ValueObject\Title;
use OCA\Tables\Service\ViewService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ViewServiceAuditLogTest extends TestCase {
	private PermissionsService&MockObject $permissionsService;
	private ViewMapper&MockObject $mapper;
	private AuditLogServiceInterface&MockObject $auditLogService;
	private ViewService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->permissionsService->method('preCheckUserId')->willReturn('alice');
		$this->mapper = $this->createMock(ViewMapper::class);
		$this->auditLogService = $this->createMock(AuditLogServiceInterface::class);

		$this->service = new ViewService(
			$this->permissionsService,
			$this->createMock(LoggerInterface::class),
			'alice',
			$this->mapper,
			$this->createMock(ShareService::class),
			$this->createMock(RowService::class),
			$this->createMock(UserHelper::class),
			$this->createMock(FavoritesService::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ContextService::class),
			$this->createMock(IL10N::class),
			$this->createMock(FederationService::class),
			$this->createMock(ActivityManager::class),
			$this->auditLogService,
			$this->createMock(FormattingRuleColMapper::class),
			$this->createMock(FormattingService::class),
		);
	}

	private function createView(): View {
		$view = new View();
		$view->setId(8);
		$view->setTableId(5);
		$view->setTitle('Open items');
		$view->setTechnicalName('view_8');
		return $view;
	}

	public function testCreateLogsCriticalAction(): void {
		$table = new Table();
		$table->setId(5);
		$table->setOwnership('alice');
		$this->permissionsService->method('canManageTable')->willReturn(true);
		$this->mapper->method('insert')->willReturnCallback(function (View $view): View {
			$view->setId(8);
			$view->setTechnicalName('view_8');
			return $view;
		});
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ViewCreated, 'View with ID: %s was created on table with ID: %s', [
				'viewId' => 8,
				'tableId' => 5,
			]);

		$this->service->create('Open items', null, $table, 'alice');
	}

	public function testUpdateFilterLogsCriticalAction(): void {
		$this->mapper->method('find')->with(8)->willReturn($this->createView());
		$this->mapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canManageView')->willReturn(true);
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ViewUpdated, 'View with ID: %s of table with ID: %s was updated', [
				'viewId' => 8,
				'tableId' => 5,
				'changedSettings' => ['filter'],
			]);

		$this->service->update(8, new ViewUpdateInput(filterSet: FilterSet::createFromInputArray([])), 'alice', true);
	}

	public function testUpdateTitleDoesNotLog(): void {
		$this->mapper->method('find')->with(8)->willReturn($this->createView());
		$this->mapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canManageView')->willReturn(true);
		$this->auditLogService->expects($this->never())->method('log');

		$this->service->update(8, new ViewUpdateInput(title: new Title('Renamed')), 'alice', true);
	}
}
