<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AuditLog\Operation;
use OCA\Tables\BackgroundJob\ImportTableJob;
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
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
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

	private function createService(ITempManager $tempManager, IAppDataFactory $appDataFactory, AuditLogServiceInterface $auditLogService, ?IRootFolder $rootFolder = null, ?IJobList $jobList = null): ImportService {
		$permissionsService = $this->createMock(PermissionsService::class);
		$permissionsService->method('canCreateRows')->willReturn(true);
		$permissionsService->method('canManageTable')->willReturn(true);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('alice')->willReturn($this->createMock(IUser::class));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l10n);

		return new ImportService(
			$permissionsService,
			$this->createMock(LoggerInterface::class),
			'alice',
			$rootFolder ?? $this->createMock(IRootFolder::class),
			$this->createMock(ColumnService::class),
			$this->createMock(RowService::class),
			$this->createMock(TableService::class),
			$this->createMock(ViewService::class),
			$userManager,
			$this->createMock(ColumnsHelper::class),
			$jobList ?? $this->createMock(IJobList::class),
			$tempManager,
			$this->createMock(IFilenameValidator::class),
			$appDataFactory,
			$auditLogService,
			$l10nFactory,
			$this->createMock(ActivityManager::class),
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
				'errors' => 0,
				'parsingErrors' => 0,
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

	public function testScheduleImportQueuesUserFileWithOriginalName(): void {
		$userFolder = $this->createMock(interface_exists(IUserFolder::class) ? IUserFolder::class : Folder::class);
		$userFolder->method('get')->with('source.csv')->willReturn($this->createMock(File::class));
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects($this->once())->method('add')->with(ImportTableJob::class, [
			'user_id' => 'alice',
			'table_id' => 5,
			'view_id' => null,
			'user_file_path' => 'source.csv',
			'import_file_name' => null,
			'original_file_name' => 'original.csv',
			'create_missing_columns' => true,
			'columns_config' => [],
		]);
		$service = $this->createService(
			$this->createMock(ITempManager::class),
			$this->createMock(IAppDataFactory::class),
			$this->createMock(AuditLogServiceInterface::class),
			$rootFolder,
			$jobList,
		);

		$service->scheduleImport(5, null, 'source.csv', originalFileName: 'original.csv');
	}

	public function testScheduleImportRejectsExistingLocalFileOutsideUserStorage(): void {
		$this->temporaryFile = tempnam(sys_get_temp_dir(), 'tables-import-');
		$userFolder = $this->createMock(interface_exists(IUserFolder::class) ? IUserFolder::class : Folder::class);
		$userFolder->method('get')->with($this->temporaryFile)->willThrowException(new NotFoundException());
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects($this->never())->method('add');
		$service = $this->createService(
			$this->createMock(ITempManager::class),
			$this->createMock(IAppDataFactory::class),
			$this->createMock(AuditLogServiceInterface::class),
			$rootFolder,
			$jobList,
		);
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Cannot find import payload');

		$service->scheduleImport(5, null, $this->temporaryFile);
	}

	public function testScheduleImportRejectsUserFolder(): void {
		$userFolder = $this->createMock(interface_exists(IUserFolder::class) ? IUserFolder::class : Folder::class);
		$userFolder->method('get')->with('folder')->willReturn($this->createMock(Folder::class));
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);
		$jobList = $this->createMock(IJobList::class);
		$jobList->expects($this->never())->method('add');
		$service = $this->createService(
			$this->createMock(ITempManager::class),
			$this->createMock(IAppDataFactory::class),
			$this->createMock(AuditLogServiceInterface::class),
			$rootFolder,
			$jobList,
		);
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Can only import from a file');

		$service->scheduleImport(5, null, 'folder');
	}
}
