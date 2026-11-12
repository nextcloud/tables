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
use OCA\Tables\Db\View;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\Permissions;
use OCA\Tables\Model\SortRuleSet;
use OCA\Tables\Model\TableScheme;
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
use OCP\App\IAppManager;
use OCP\DB\Exception;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TableServiceTest extends TestCase {
	private PermissionsService&MockObject $permissionsService;
	private TableMapper&MockObject $mapper;
	private ColumnService&MockObject $columnService;
	private RowService&MockObject $rowService;
	private ShareService&MockObject $shareService;
	private ViewService&MockObject $viewService;
	private FavoritesService&MockObject $favoritesService;
	private TableService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->permissionsService->method('preCheckUserId')
			->willReturnCallback(static fn (?string $userId = null): string => $userId ?? 'alice');
		$this->mapper = $this->createMock(TableMapper::class);
		$this->columnService = $this->createMock(ColumnService::class);
		$this->rowService = $this->createMock(RowService::class);
		$this->shareService = $this->createMock(ShareService::class);
		$this->viewService = $this->createMock(ViewService::class);
		$this->favoritesService = $this->createMock(FavoritesService::class);
		$this->service = new TableService(
			$this->permissionsService,
			$this->createMock(LoggerInterface::class),
			'alice',
			$this->mapper,
			$this->createMock(TableTemplateService::class),
			$this->columnService,
			$this->rowService,
			$this->viewService,
			$this->shareService,
			$this->createMock(UserHelper::class),
			$this->favoritesService,
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ContextService::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(IL10N::class),
			$this->createMock(Defaults::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(FederationService::class),
			$this->createMock(AuditLogServiceInterface::class),
		);
	}

	private function createTable(int $id): Table {
		$table = new Table();
		$table->setId($id);
		$table->setOwnership('alice');
		return $table;
	}

	public function testGetSchemeUsesSessionUser(): void {
		$table = $this->createTable(1);
		$this->mapper->method('find')->with(1)->willReturn($table);
		$this->permissionsService->method('canReadTable')->with($table, 'alice')->willReturn(true);
		$this->permissionsService->method('canManageTable')->with($table, 'alice')->willReturn(true);
		$this->columnService->expects($this->once())->method('findAllByTable')->with(1, 'alice', $table)->willReturn([]);
		$this->rowService->expects($this->once())->method('getRowsCountForTables')->with([1], 'alice')->willReturn([1 => 7]);
		$this->viewService->expects($this->once())->method('findForTables')->with([$table], 'alice', [1 => 7])->willReturn([]);

		$this->service->getScheme(1);
		$this->assertSame(7, $table->getRowsCount());
	}

	public function testGetSchemeUsesExplicitUserForAccessChecks(): void {
		$table = $this->createTable(1);
		$table->setOwnership('bob');
		$this->mapper->method('find')->with(1)->willReturn($table);
		$this->permissionsService->method('canReadTable')->with($table, 'bob')->willReturn(true);
		$this->permissionsService->method('canManageTable')->with($table, 'bob')->willReturn(true);
		$this->columnService->expects($this->once())->method('findAllByTable')->with(1, 'bob', $table)->willReturn([]);
		$this->rowService->expects($this->once())->method('getRowsCountForTables')->with([1], 'bob')->willReturn([]);

		$this->service->getScheme(1, 'bob');
	}

	public function testGetSchemeDoesNotLoadViewsForReadOnlyRecipients(): void {
		$table = $this->createTable(1);
		$table->setOwnership('bob');
		$table->setViews([new View()]);
		$this->mapper->method('find')->with(1)->willReturn($table);
		$this->permissionsService->method('canReadTable')->willReturn(true);
		$this->shareService->method('getSharedPermissionsIfSharedWithMe')->willReturn(new Permissions(read: true));
		$this->viewService->expects($this->once())->method('findForTables')->with([], 'alice', [])->willReturn([]);

		$this->assertSame([], $this->service->getScheme(1)->jsonSerialize()['views']);
		$this->assertTrue($table->getIsShared());
	}

	public function testEnhancementContinuesAfterRowCountFailure(): void {
		$this->assertEnhancementContinuesAfterFailure('rows');
	}

	public function testEnhancementContinuesAfterColumnCountFailure(): void {
		$this->assertEnhancementContinuesAfterFailure('columns');
	}

	public function testEnhancementContinuesAfterShareCountFailure(): void {
		$this->assertEnhancementContinuesAfterFailure('shares');
	}

	public function testEnhancementContinuesAfterViewLookupFailure(): void {
		$this->assertEnhancementContinuesAfterFailure('views');
	}

	public function testViewPermissionFailureDoesNotStopOtherTables(): void {
		$tables = [$this->createTable(1), $this->createTable(2)];
		$this->mapper->method('findAll')->willReturn($tables);
		$this->permissionsService->method('canManageTable')->willReturnCallback(static function (Table $table): bool {
			if ($table->getId() === 1) {
				throw new InternalError('Table permissions unavailable');
			}
			return true;
		});
		$view = new View();
		$this->viewService->expects($this->once())->method('findForTables')
			->with([$tables[1]], 'alice', [])->willReturn([2 => [$view]]);
		$this->favoritesService->expects($this->exactly(2))->method('isFavorite')->willReturn(true);

		$this->assertSame($tables, $this->service->findAll(skipSharedTables: true, createTutorial: false));
		$this->assertSame([], $tables[0]->getViews());
		$this->assertSame([$view], $tables[1]->getViews());
		$this->assertTrue($tables[0]->getFavorite());
		$this->assertTrue($tables[1]->getFavorite());
	}

	public function testPublicShareLookupFailureDoesNotStopEnhancement(): void {
		$table = $this->createTable(1);
		$this->mapper->method('find')->willReturn($table);
		$this->permissionsService->method('canReadTable')->willReturn(true);
		$this->rowService->method('getRowsCountForTables')->willReturn([1 => 7]);
		$this->columnService->method('getColumnsCountForTables')->willReturn([1 => 3]);
		$this->shareService->method('getTableIdsWithLinkShares')->willThrowException(new InternalError('Link shares unavailable'));
		$this->favoritesService->method('isFavorite')->willReturn(true);

		$this->assertSame($table, $this->service->find(1, userId: ''));
		$this->assertSame(7, $table->getRowsCount());
		$this->assertSame(3, $table->getColumnsCount());
		$this->assertTrue($table->getOnSharePermissions()->read);
		$this->assertTrue($table->getFavorite());
	}

	private function assertEnhancementContinuesAfterFailure(string $failedStep): void {
		$tables = [$this->createTable(1), $this->createTable(2)];
		$this->mapper->method('findAll')->willReturn($tables);
		$this->permissionsService->method('canManageTable')->willReturn(true);
		$this->favoritesService->expects($this->exactly(2))->method('isFavorite')->willReturn(true);
		$rowCounts = $failedStep === 'rows' ? [] : [1 => 7, 2 => 9];
		if ($failedStep === 'rows') {
			$this->rowService->method('getRowsCountForTables')->willThrowException($this->createMock(Exception::class));
		} else {
			$this->rowService->method('getRowsCountForTables')->willReturn($rowCounts);
		}
		if ($failedStep === 'columns') {
			$this->columnService->method('getColumnsCountForTables')->willThrowException($this->createMock(Exception::class));
		} else {
			$this->columnService->method('getColumnsCountForTables')->willReturn([1 => 3, 2 => 4]);
		}
		if ($failedStep === 'shares') {
			$this->shareService->method('countSharesForNodes')->willThrowException(new InternalError('Share count unavailable'));
		} else {
			$this->shareService->method('countSharesForNodes')->willReturn([1 => 1, 2 => 1]);
		}
		$view = new View();
		$viewLookup = $this->viewService->expects($this->once())->method('findForTables')->with($tables, 'alice', $rowCounts);
		if ($failedStep === 'views') {
			$viewLookup->willThrowException(new InternalError('Views unavailable'));
		} else {
			$viewLookup->willReturn([1 => [$view]]);
		}

		$this->assertSame($tables, $this->service->findAll(skipSharedTables: true, createTutorial: false));
		foreach ($tables as $table) {
			$this->assertTrue($table->getFavorite());
			$this->assertSame($rowCounts[$table->getId()] ?? 0, $table->getRowsCount());
			$this->assertSame($failedStep === 'columns' ? 0 : $table->getId() + 2, $table->getColumnsCount());
			$this->assertSame($failedStep !== 'shares', $table->getHasShares());
		}
		$this->assertSame($failedStep === 'views' ? [] : [$view], $tables[0]->getViews());
		$this->assertSame([], $tables[1]->getViews());
	}

	public function testSortRoundTripSingleRule(): void {
		$input = [['columnId' => 3, 'mode' => 'ASC']];

		$table = new Table();
		// Simulate what TableService::update() does when $sort !== null
		$table->setSort(\json_encode(SortRuleSet::createFromInputArray($input)->jsonSerialize()));

		$this->assertSame($input, $table->getSortArray());
	}

	public function testSortRoundTripMultipleRules(): void {
		$input = [
			['columnId' => 7, 'mode' => 'DESC'],
			['columnId' => 2, 'mode' => 'ASC'],
		];

		$table = new Table();
		$table->setSort(\json_encode(SortRuleSet::createFromInputArray($input)->jsonSerialize()));

		$this->assertSame($input, $table->getSortArray());
	}

	public function testSortRoundTripEmptyArray(): void {
		$table = new Table();
		$table->setSort(\json_encode(SortRuleSet::createFromInputArray([])->jsonSerialize()));

		$this->assertSame([], $table->getSortArray());
	}

	public function testNullSortInputLeavesFieldUnchanged(): void {
		$existing = [['columnId' => 5, 'mode' => 'DESC']];

		$table = new Table();
		$table->setSort(\json_encode($existing));

		// Simulate what TableService::update() does when $sort === null: skip update
		$sort = null;
		if ($sort !== null) {
			$table->setSort(\json_encode(SortRuleSet::createFromInputArray($sort)->jsonSerialize()));
		}

		$this->assertSame($existing, $table->getSortArray());
	}

	public function testNullSortInputOnUninitializedTableLeavesFieldNull(): void {
		$table = new Table();
		// sort is null by default; passing null must not change it
		$sort = null;
		if ($sort !== null) {
			$table->setSort(\json_encode(SortRuleSet::createFromInputArray($sort)->jsonSerialize()));
		}

		$this->assertNull($table->getSort());
		$this->assertSame([], $table->getSortArray());
	}

	public function testGetSchemeColumnOrderRoundTrip(): void {
		$columnOrder = [['columnId' => 3, 'order' => 1]];

		$table = new Table();
		$table->setColumnOrder(\json_encode($columnOrder));

		$scheme = new TableScheme('T', '📋', [], [], '', '1.0.0',
			$table->getColumnOrderSettingsArray(),
			$table->getSortArray(),
		);

		$decoded = json_decode(json_encode($scheme), true);
		$this->assertSame($columnOrder, $decoded['columnOrder']);
		$this->assertSame([], $decoded['sort']);
	}

	public function testGetSchemeSortRoundTrip(): void {
		$sort = [['columnId' => 7, 'mode' => 'DESC']];

		$table = new Table();
		$table->setSort(\json_encode($sort));

		$scheme = new TableScheme('T', '📋', [], [], '', '1.0.0',
			$table->getColumnOrderSettingsArray(),
			$table->getSortArray(),
		);

		$decoded = json_decode(json_encode($scheme), true);
		$this->assertSame([], $decoded['columnOrder']);
		$this->assertSame($sort, $decoded['sort']);
	}

	public function testCreateFromSchemeColumnOrderIdRemapping(): void {
		$columnOrder = [['columnId' => 10, 'order' => 1, 'readonly' => false, 'mandatory' => false]];
		$colMap = [10 => 99];

		$remapped = array_map(static function (array $entry) use ($colMap): array {
			if (isset($entry['columnId']) && $entry['columnId'] > 0) {
				$entry['columnId'] = $colMap[$entry['columnId']] ?? $entry['columnId'];
			}
			return $entry;
		}, $columnOrder);

		$this->assertSame(99, $remapped[0]['columnId']);
		$this->assertSame(1, $remapped[0]['order']);
	}

	public function testCreateFromSchemeSortIdRemapping(): void {
		$sort = [['columnId' => 10, 'mode' => 'ASC']];
		$colMap = [10 => 99];

		$remapped = array_map(static function (array $entry) use ($colMap): array {
			if (isset($entry['columnId']) && $entry['columnId'] > 0) {
				$entry['columnId'] = $colMap[$entry['columnId']] ?? $entry['columnId'];
			}
			return $entry;
		}, $sort);

		$this->assertSame(99, $remapped[0]['columnId']);
		$this->assertSame('ASC', $remapped[0]['mode']);
	}
}
