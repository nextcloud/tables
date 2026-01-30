<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit;

use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\RowCellDatetimeMapper;
use OCA\Tables\Db\RowCellNumberMapper;
use OCA\Tables\Db\RowCellSelectionMapper;
use OCA\Tables\Db\RowCellTextMapper;
use OCA\Tables\Db\RowCellUsergroupMapper;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\FavoritesService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCA\Tables\UserMigration\TablesMigrator;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

class TablesMigratorTest extends TestCase {
	private TablesMigrator $migrator;
	private $l10n;
	private $tableMapper;
	private $columnMapper;
	private $rowSleeveMapper;
	private $viewMapper;
	private $contextMapper;
	private $shareMapper;
	private $contextNodeRelationMapper;
	private $favoritesService;
	private $tableService;
	private $rowCellNumberMapper;
	private $rowCellSelectionMapper;
	private $rowCellTextMapper;
	private $rowCellUsergroupMapper;
	private $rowCellDatetimeMapper;
	private $viewService;
	private $columnService;
	private $rowService;
	private $contextService;
	private $shareService;

	protected function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->columnMapper = $this->createMock(ColumnMapper::class);
		$this->rowSleeveMapper = $this->createMock(RowSleeveMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->contextNodeRelationMapper = $this->createMock(ContextNodeRelationMapper::class);
		$this->favoritesService = $this->createMock(FavoritesService::class);
		$this->tableService = $this->createMock(TableService::class);
		$this->rowCellNumberMapper = $this->createMock(RowCellNumberMapper::class);
		$this->rowCellSelectionMapper = $this->createMock(RowCellSelectionMapper::class);
		$this->rowCellTextMapper = $this->createMock(RowCellTextMapper::class);
		$this->rowCellUsergroupMapper = $this->createMock(RowCellUsergroupMapper::class);
		$this->rowCellDatetimeMapper = $this->createMock(RowCellDatetimeMapper::class);
		$this->viewService = $this->createMock(ViewService::class);
		$this->columnService = $this->createMock(ColumnService::class);
		$this->rowService = $this->createMock(RowService::class);
		$this->contextService = $this->createMock(ContextService::class);
		$this->shareService = $this->createMock(ShareService::class);

		$this->migrator = new TablesMigrator(
			$this->l10n,
			$this->tableMapper,
			$this->columnMapper,
			$this->rowSleeveMapper,
			$this->viewMapper,
			$this->contextMapper,
			$this->shareMapper,
			$this->contextNodeRelationMapper,
			$this->favoritesService,
			$this->tableService,
			$this->rowCellNumberMapper,
			$this->rowCellSelectionMapper,
			$this->rowCellTextMapper,
			$this->rowCellUsergroupMapper,
			$this->rowCellDatetimeMapper,
			$this->viewService,
			$this->columnService,
			$this->rowService,
			$this->contextService,
			$this->shareService
		);
	}

	public function testGetId(): void {
		$this->assertSame('tables', $this->migrator->getId());
	}

	public function testGetDisplayName(): void {
		$this->l10n->method('t')->willReturn('Tables');
		$this->assertSame('Tables', $this->migrator->getDisplayName());
	}

	public function testGetDescription(): void {
		$this->l10n->method('t')->willReturn('desc');
		$this->assertSame('desc', $this->migrator->getDescription());
	}

	public function testGetEstimatedExportSize(): void {
		$user = $this->createMock(IUser::class);
		$this->assertSame(0, $this->migrator->getEstimatedExportSize($user));
	}

	public function testExportSuccess(): void {
		$user = $this->createMock(IUser::class);
		$exportDestination = $this->createMock(IExportDestination::class);
		$output = $this->createMock(NullOutput::class);

		$user->method('getUID')->willReturn('user1');
		$this->favoritesService->method('findAll')->willReturn([]);
		$this->tableMapper->method('findAll')->willReturn([]);
		$this->columnMapper->method('findAllByTableIds')->willReturn([]);
		$this->rowSleeveMapper->method('findAllByTableIds')->willReturn([]);
		$this->viewMapper->method('findAll')->willReturn([]);
		$this->contextMapper->method('findAll')->willReturn([]);
		$this->shareMapper->method('findAllSharesForTablesAndContexts')->willReturn([]);
		$this->rowCellNumberMapper->method('findAllByRowIdsAndColumnIds')->willReturn([]);
		$this->rowCellSelectionMapper->method('findAllByRowIdsAndColumnIds')->willReturn([]);
		$this->rowCellTextMapper->method('findAllByRowIdsAndColumnIds')->willReturn([]);
		$this->rowCellDatetimeMapper->method('findAllByRowIdsAndColumnIds')->willReturn([]);
		$this->rowCellUsergroupMapper->method('findAllByRowIdsAndColumnIds')->willReturn([]);

		$exportDestination->expects($this->atLeastOnce())->method('addFileContents');
		$this->migrator->export($user, $exportDestination, $output);
		$this->assertTrue(true);
	}

	public function testExportFailure(): void {
		$user = $this->createMock(IUser::class);
		$exportDestination = $this->createMock(IExportDestination::class);
		$output = $this->createMock(NullOutput::class);

		$user->method('getUID')->willReturn('user1');
		$this->favoritesService->method('findAll')->willThrowException(new \Exception('fail'));

		$this->expectException(\Exception::class);
		$this->migrator->export($user, $exportDestination, $output);
	}

	public function testImportSuccess(): void {
		$user = $this->createMock(IUser::class);
		$importSource = $this->createMock(IImportSource::class);
		$output = $this->createMock(NullOutput::class);

		$user->method('getUID')->willReturn('user1');

		$importSource->method('getMigratorVersion')->willReturn(1);
		$importSource->method('getFileContents')->willReturn(json_encode([]));

		$this->tableMapper->method('getDBConnection')->willReturn(new class {
			public function beginTransaction() {
			}
			public function commit() {
			}
			public function rollBack() {
			}
		});

		$this->tableService->method('importTable')->willReturn($this->createMock(Table::class));
		$this->favoritesService->method('findAll')->willReturn([]);
		$this->columnService->method('importColumn')->willReturn(1);
		$this->rowService->method('importRow')->willReturn(1);
		$this->viewService->method('importView');
		$this->shareService->method('importShare');
		$this->contextService->method('importContext')->willReturn($this->createMock(Context::class));

		$output->expects($this->atLeastOnce())->method('writeln');
		$this->migrator->import($user, $importSource, $output);
		$this->assertTrue(true);
	}

	public function testImportFailure(): void {
		$user = $this->createMock(IUser::class);
		$importSource = $this->createMock(IImportSource::class);
		$output = $this->createMock(NullOutput::class);

		$importSource->method('getMigratorVersion')->willReturn(1);
		$importSource->method('getFileContents')->willThrowException(new \Exception('fail'));

		$this->tableMapper->method('getDBConnection')->willReturn(new class {
			public function beginTransaction() {
			}
			public function commit() {
			}
			public function rollBack() {
			}
		});

		$this->expectException(\Exception::class);
		$this->migrator->import($user, $importSource, $output);
	}

	public function testImportWithInvalidJson(): void {
		$user = $this->createMock(IUser::class);
		$importSource = $this->createMock(IImportSource::class);
		$output = $this->createMock(NullOutput::class);

		$user->method('getUID')->willReturn('user1');
		$importSource->method('getMigratorVersion')->willReturn(1);

		$importSource->method('getFileContents')->willReturn('{invalid json');

		$this->tableMapper->method('getDBConnection')->willReturn(new class {
			public function beginTransaction() {
			}
			public function commit() {
			}
			public function rollBack() {
			}
		});

		$output->expects($this->atLeastOnce())->method('writeln');
		$this->expectException(\Exception::class);
		$this->migrator->import($user, $importSource, $output);
	}
}
