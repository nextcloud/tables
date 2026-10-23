<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ImportService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\BackgroundJob\IJobList;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ITempManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ImportServiceAuditLogTest extends TestCase {
	private string $temporaryFile = '';

	protected function tearDown(): void {
		if ($this->temporaryFile !== '' && file_exists($this->temporaryFile)) {
			unlink($this->temporaryFile);
		}
		parent::tearDown();
	}

	private function createService(ITempManager $tempManager, IAppDataFactory $appDataFactory, AuditLogServiceInterface $auditLogService): ImportService {
		return new ImportService(
			$this->createMock(PermissionsService::class),
			$this->createMock(LoggerInterface::class),
			'alice',
			$this->createMock(IRootFolder::class),
			$this->createMock(ColumnService::class),
			$this->createMock(RowService::class),
			$this->createMock(TableService::class),
			$this->createMock(ViewService::class),
			$this->createMock(IUserManager::class),
			$this->createMock(ColumnsHelper::class),
			$this->createMock(IJobList::class),
			$tempManager,
			$appDataFactory,
			$auditLogService,
		);
	}

	public function testImportV2LogsCountsWithoutValues(): void {
		$this->temporaryFile = tempnam(sys_get_temp_dir(), 'tables-import-') . '.csv';
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getExtension')->willReturn('csv');
		$file->method('getContent')->willReturn("Title\n");
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->with('import.csv')->willReturn($file);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->with('import')->willReturn($folder);
		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$appDataFactory->method('get')->willReturn($appData);
		$tempManager = $this->createMock(ITempManager::class);
		$tempManager->method('getTemporaryFile')->willReturn($this->temporaryFile);
		$auditLogService = $this->createMock(AuditLogServiceInterface::class);

		$service = $this->createService($tempManager, $appDataFactory, $auditLogService);

		$auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ImportCompleted, 'Rows were imported into %s with ID: %s by user with ID: %s', [
				'nodeType' => 'table',
				'nodeId' => 5,
				'userId' => 'alice',
				'insertedRows' => 0,
				'updatedRows' => 0,
				'createdColumns' => 0,
			]);

		$service->importV2('alice', ImportService::IMPORT_TYPE_APP_FILE, 'import.csv', 5, null);
	}

	public function testFailedImportDoesNotLog(): void {
		$auditLogService = $this->createMock(AuditLogServiceInterface::class);
		$auditLogService->expects($this->never())->method('log');
		$service = $this->createService(
			$this->createMock(ITempManager::class),
			$this->createMock(IAppDataFactory::class),
			$auditLogService,
		);

		$this->expectException(NotFoundError::class);

		$service->importV2('alice', 'unknown', 'import.csv', 5, null);
	}
}
