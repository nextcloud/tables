<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Row2;
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
use Psr\Log\LoggerInterface;

final class ImportServiceUpsertRowTest extends TestCase {
	private const TABLE_ID = 5;
	private const TITLE_COLUMN_ID = 21;

	private string $temporaryFile = '';

	protected function tearDown(): void {
		if ($this->temporaryFile !== '' && file_exists($this->temporaryFile)) {
			unlink($this->temporaryFile);
		}
		parent::tearDown();
	}

	public function testARowIdThatIsNotReachableCountsAsAnErrorAndTheImportContinues(): void {
		$rowService = $this->createMock(RowService::class);
		$rowService->expects($this->exactly(2))
			->method('updateSet')
			->willReturnCallback(function (int $rowId): Row2 {
				if ($rowId === 99) {
					throw new NotFoundError('Row does not belong to table with id ' . self::TABLE_ID);
				}
				return new Row2();
			});

		$stats = $this->createService("ID,Title\n99,foreign\n100,own\n", $rowService)->importV2(
			'alice',
			ImportService::IMPORT_TYPE_APP_FILE,
			'import.csv',
			self::TABLE_ID,
			null,
			false,
			[
				['action' => 'exist', 'existColumn' => ['id' => Column::TYPE_META_ID, 'label' => 'ID']],
				['action' => 'exist', 'existColumn' => ['id' => self::TITLE_COLUMN_ID, 'label' => 'Title']],
			],
		);

		$this->assertSame(1, $stats->errorsCount);
		$this->assertSame(1, $stats->updatedRowsCount);
		$this->assertSame(0, $stats->insertedRowsCount);
	}

	private function createService(string $csv, RowService $rowService): ImportService {
		$this->temporaryFile = tempnam(sys_get_temp_dir(), 'tables-import-') . '.csv';
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getExtension')->willReturn('csv');
		$file->method('getContent')->willReturn($csv);
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->with('import.csv')->willReturn($file);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->with('import')->willReturn($folder);
		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$appDataFactory->method('get')->willReturn($appData);
		$tempManager = $this->createMock(ITempManager::class);
		$tempManager->method('getTemporaryFile')->willReturn($this->temporaryFile);

		$titleColumn = new Column();
		$titleColumn->setId(self::TITLE_COLUMN_ID);
		$titleColumn->setTitle('Title');
		$titleColumn->setType(Column::TYPE_TEXT);
		$titleColumn->setMandatory(false);
		$columnService = $this->createMock(ColumnService::class);
		$columnService->method('findOrCreateColumnsByTitleForTableAsArray')->willReturn([$titleColumn]);

		$business = $this->createMock(IColumnTypeBusiness::class);
		$business->method('canBeParsedDisplayValue')->willReturn(true);
		$business->method('parseDisplayValue')->willReturnCallback(fn (string $value): string => json_encode($value));
		$columnsHelper = $this->createMock(ColumnsHelper::class);
		$columnsHelper->method('getColumnBusinessObject')->willReturn($business);

		return new ImportService(
			$this->createMock(PermissionsService::class),
			$this->createMock(LoggerInterface::class),
			'alice',
			$this->createMock(IRootFolder::class),
			$columnService,
			$rowService,
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
}
