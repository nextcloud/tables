<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ColumnTypes\IColumnTypeBusiness;
use OCA\Tables\Service\ImportService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\BackgroundJob\IJobList;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ITempManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ImportServiceRowUpdateTest extends TestCase {
	private const CSV = "ID,Title\n999,update me\n,create me\n";

	private string $temporaryFile = '';
	private RowService $rows;

	protected function setUp(): void {
		parent::setUp();
		$this->temporaryFile = sys_get_temp_dir() . '/' . uniqid('tables-import-', true) . '.csv';
		$this->rows = $this->createMock(RowService::class);
	}

	protected function tearDown(): void {
		if (file_exists($this->temporaryFile)) {
			unlink($this->temporaryFile);
		}
		parent::tearDown();
	}

	private function column(int $id, string $title): Column {
		$column = new Column();
		$column->setId($id);
		$column->setTitle($title);
		$column->setType(Column::TYPE_TEXT);
		$column->setSubtype(Column::SUBTYPE_TEXT_LINE);
		$column->setMandatory(false);
		return $column;
	}

	private function createService(): ImportService {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getExtension')->willReturn('csv');
		$file->method('getContent')->willReturn(self::CSV);
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->willReturn($file);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willReturn($folder);
		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$appDataFactory->method('get')->willReturn($appData);
		$tempManager = $this->createMock(ITempManager::class);
		$tempManager->method('getTemporaryFile')->willReturn($this->temporaryFile);

		$columns = $this->createMock(ColumnService::class);
		$columns->method('findOrCreateColumnsByTitleForTableAsArray')->willReturn([
			$this->column(Column::TYPE_META_ID, 'ID'),
			$this->column(7, 'Title'),
		]);
		$business = $this->createMock(IColumnTypeBusiness::class);
		$business->method('canBeParsedDisplayValue')->willReturn(true);
		$business->method('parseDisplayValue')->willReturn('"parsed"');
		$columnsHelper = $this->createMock(ColumnsHelper::class);
		$columnsHelper->method('getColumnBusinessObject')->willReturn($business);

		return new ImportService(
			$this->createMock(PermissionsService::class),
			new NullLogger(),
			'alice',
			$this->createMock(IRootFolder::class),
			$columns,
			$this->rows,
			$this->createMock(TableService::class),
			$this->createMock(ViewService::class),
			$this->createMock(IUserManager::class),
			$columnsHelper,
			$this->createMock(IJobList::class),
			$tempManager,
			$this->createMock(IFilenameValidator::class),
			$appDataFactory,
			$this->createMock(AuditLogServiceInterface::class),
		);
	}

	public function testRowMissingFromDestinationFailsOnlyThatLine(): void {
		$this->rows->expects($this->once())->method('updateSet')
			->with(999, null, [['columnId' => 7, 'value' => 'parsed']], 'alice', 5)
			->willThrowException(new NotFoundError('Row not found.'));
		$this->rows->expects($this->once())->method('create');

		$stats = $this->createService()->importV2('alice', ImportService::IMPORT_TYPE_APP_FILE, 'import.csv', 5, null);

		$this->assertSame(1, $stats->errorsCount);
		$this->assertSame(0, $stats->updatedRowsCount);
		$this->assertSame(1, $stats->insertedRowsCount);
	}

	public function testMissingDestinationStillAbortsTheImport(): void {
		$this->rows->method('updateSet');
		$this->rows->method('create')->willThrowException(new NotFoundError('Table not found.'));

		$this->expectException(NotFoundError::class);

		$this->createService()->importV2('alice', ImportService::IMPORT_TYPE_APP_FILE, 'import.csv', 5, null);
	}
}
