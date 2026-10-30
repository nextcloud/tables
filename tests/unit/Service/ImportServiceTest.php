<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Model\ImportProblem;
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
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\IUserFolder;
use OCP\Files\Storage\IStorage;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SplFileObject;

class ImportServiceTest extends TestCase {
	private ImportService $service;
	private RowService&MockObject $rowService;
	private ActivityManager&MockObject $activityManager;
	private Column $column;
	private string $sourcePath;
	private string $reportPath;
	private string $storedReport = '';
	private bool $reportAvailable = true;
	private ?RuntimeException $storageFailure = null;

	protected function setUp(): void {
		parent::setUp();
		$this->sourcePath = tempnam(sys_get_temp_dir(), 'tables-import-source-');
		$this->reportPath = tempnam(sys_get_temp_dir(), 'tables-import-report-');

		$sourceFile = $this->createMock(File::class);
		$storage = $this->createMock(IStorage::class);
		$storage->method('getLocalFile')->willReturn($this->sourcePath);
		$sourceFile->method('getStorage')->willReturn($storage);
		$sourceFile->method('getInternalPath')->willReturn('source.csv');

		$userFolder = $this->createMock(interface_exists(IUserFolder::class) ? IUserFolder::class : Folder::class);
		$tablesFolder = $this->createMock(Folder::class);
		$importsFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->willReturnMap([
			['source.csv', $sourceFile],
			['Tables', $tablesFolder],
		]);
		$tablesFolder->method('get')->with('Imports')->willReturn($importsFolder);
		$userFolder->method('getRelativePath')->willReturn('/Tables/Imports/report.csv');
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);

		$reportFile = $this->createMock(File::class);
		$reportFile->method('getId')->willReturn(42);
		$reportFile->method('getName')->willReturn('report.csv');
		$reportFile->method('getPath')->willReturn('/alice/files/Tables/Imports/report.csv');
		$reportFile->method('putContent')->willReturnCallback(function ($stream): void {
			self::assertIsResource($stream);
			if ($this->storageFailure !== null) {
				throw $this->storageFailure;
			}
			$this->storedReport = stream_get_contents($stream);
		});
		$importsFolder->method('getNonExistingName')->willReturnArgument(0);
		$importsFolder->method('newFile')->willReturn($reportFile);

		$tempManager = $this->createMock(ITempManager::class);
		$tempManager->method('getTemporaryFile')->willReturnCallback(fn () => $this->reportAvailable ? $this->reportPath : false);
		$user = $this->createMock(IUser::class);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('alice')->willReturn($user);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $parameters = []): string => vsprintf($text, $parameters));
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->expects(self::once())->method('getUserLanguage')->with($user)->willReturn('fr');
		$l10nFactory->expects(self::once())->method('get')->with(Application::APP_ID, 'fr')->willReturn($l10n);

		$this->column = new Column();
		$this->column->setId(1);
		$this->column->setTitle('Name');
		$this->column->setType(Column::TYPE_TEXT);
		$this->column->setSubtype(Column::SUBTYPE_TEXT_LINE);
		$columnService = $this->createMock(ColumnService::class);
		$columnService->method('findOrCreateColumnsByTitleForTableAsArray')->willReturn([$this->column]);
		$columnBusiness = $this->createMock(IColumnTypeBusiness::class);
		$columnBusiness->method('canBeParsedDisplayValue')->willReturn(false);
		$columnsHelper = $this->createMock(ColumnsHelper::class);
		$columnsHelper->method('getColumnBusinessObject')->willReturn($columnBusiness);
		$this->rowService = $this->createMock(RowService::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$tableService = $this->createMock(TableService::class);
		$table = new Table();
		$table->setId(1);
		$tableService->method('find')->willReturn($table);
		$appDataFactory = $this->createMock(IAppDataFactory::class);
		$appDataFactory->method('get')->willReturn($this->createMock(IAppData::class));
		$filenameValidator = $this->createMock(IFilenameValidator::class);
		$filenameValidator->method('isFilenameValid')->willReturn(true);

		$this->service = new ImportService(
			$this->createMock(PermissionsService::class),
			$this->createMock(LoggerInterface::class),
			null,
			$rootFolder,
			$columnService,
			$this->rowService,
			$tableService,
			$this->createMock(ViewService::class),
			$userManager,
			$columnsHelper,
			$this->createMock(IJobList::class),
			$tempManager,
			$filenameValidator,
			$appDataFactory,
			$this->createMock(AuditLogServiceInterface::class),
			$l10nFactory,
			$this->activityManager,
		);
	}

	protected function tearDown(): void {
		unlink($this->sourcePath);
		unlink($this->reportPath);
		parent::tearDown();
	}

	/**
	 * @dataProvider previewModes
	 */
	public function testFullReportIsStreamedAndPreviewIsOnlyCollectedWhenRequested(bool $collectProblemsPreview): void {
		$source = "Name\n";
		for ($index = 0; $index < 80; $index++) {
			$source .= 'invalid-' . $index . "\n";
		}
		file_put_contents($this->sourcePath, $source);
		$this->activityManager->expects(self::once())->method('triggerEvent')->with(
			ActivityManager::TABLES_OBJECT_TABLE,
			self::anything(),
			ActivityManager::SUBJECT_IMPORT_FINISHED,
			self::callback(static fn (array $parameters): bool => $parameters['importStats']->problems === []),
			'alice',
		);

		$stats = $this->service->importV2('alice', ImportService::IMPORT_TYPE_USER_FILE, 'source.csv', 1, null, collectProblemsPreview: $collectProblemsPreview);

		self::assertSame(80, $stats->errorsParsingCount);
		self::assertCount($collectProblemsPreview ? 50 : 0, $stats->problems);
		self::assertSame(80, substr_count($this->storedReport, ImportProblem::CODE_PARSE_FAILED));
		self::assertStringContainsString('invalid-79', $this->storedReport);
		self::assertSame(42, $stats->problemsFile->id);
	}

	public static function previewModes(): array {
		return [
			'synchronous' => [true],
			'background' => [false],
		];
	}

	/**
	 * @dataProvider dangerousColumnTitles
	 */
	public function testCsvFormulaPrefixesAreEscapedButPreviewValuesArePreserved(string $columnTitle): void {
		$this->column->setTitle($columnTitle);
		file_put_contents($this->sourcePath, "Name\n@SUM(1)\n'@literal\n");

		$stats = $this->service->importV2('alice', ImportService::IMPORT_TYPE_USER_FILE, 'source.csv', 1, null);

		$report = new SplFileObject($this->reportPath, 'r');
		$report->fgetcsv(',', '"', '');
		$record = $report->fgetcsv(',', '"', '');
		self::assertSame(str_starts_with($columnTitle, "'") ? $columnTitle : "'" . $columnTitle, $record[1]);
		self::assertStringContainsString("'@SUM(1)", $this->storedReport);
		self::assertSame($columnTitle, $stats->problems[0]->column);
		self::assertSame('@SUM(1)', $stats->problems[0]->value);
		self::assertSame("'@literal", $stats->problems[1]->value);
	}

	public static function dangerousColumnTitles(): array {
		return [
			'formula' => ['=1+1'],
			'hyperlink' => ['=HYPERLINK("https://example.test", "Click")'],
			'plus' => ['+SUM(1)'],
			'minus' => ['-SUM(1)'],
			'at' => ['@SUM(1)'],
			'whitespace formula' => [" \t=1+1"],
			'tab' => ["\ttext"],
			'carriage return' => ["\rtext"],
			'newline' => ["\ntext"],
			'apostrophe' => ["'@literal"],
		];
	}

	public function testOnlyThePreviewTruncatesProblemValues(): void {
		$value = str_repeat('invalid', 20);
		file_put_contents($this->sourcePath, "Name\n" . $value . "\n");

		$stats = $this->service->importV2('alice', ImportService::IMPORT_TYPE_USER_FILE, 'source.csv', 1, null);

		self::assertStringContainsString($value, $this->storedReport);
		self::assertSame(51, mb_strlen($stats->problems[0]->value));
		self::assertStringStartsWith(substr($value, 0, 50), $stats->problems[0]->value);
	}

	public function testFailurePublishesSafeActivityWithoutSavingReport(): void {
		file_put_contents($this->sourcePath, "Name\ninvalid\n");
		$failure = new NotFoundError('SQL error at /internal/path');
		$this->rowService->method('create')->willThrowException($failure);
		$this->activityManager->expects(self::once())->method('triggerEvent')->with(
			ActivityManager::TABLES_OBJECT_TABLE,
			self::anything(),
			ActivityManager::SUBJECT_IMPORT_FAILED,
			self::callback(function (array $parameters): bool {
				self::assertSame('', $this->storedReport);
				self::assertNotEmpty($parameters['errorMessage']);
				self::assertStringNotContainsString('SQL error', $parameters['errorMessage']);
				self::assertStringNotContainsString('/internal/path', $parameters['errorMessage']);
				self::assertArrayNotHasKey('importProblemsFile', $parameters);
				self::assertArrayNotHasKey('importStats', $parameters);
				return true;
			}),
			'alice',
		);

		try {
			$this->service->importV2('alice', ImportService::IMPORT_TYPE_USER_FILE, 'source.csv', 1, null);
			self::fail('The import failure must be rethrown');
		} catch (NotFoundError $exception) {
			self::assertSame($failure, $exception->getPrevious());
			self::assertNull($this->service->getStoredProblemsFile());
		}
	}

	public function testReportStorageFailureDoesNotFailCompletedImport(): void {
		file_put_contents($this->sourcePath, "Name\ninvalid\n");
		$this->storageFailure = new RuntimeException('Report storage unavailable');
		$this->activityManager->expects(self::once())->method('triggerEvent')->with(
			ActivityManager::TABLES_OBJECT_TABLE,
			self::anything(),
			ActivityManager::SUBJECT_IMPORT_FINISHED,
			self::callback(static fn (array $parameters): bool => $parameters['importProblemsFile'] === null),
			'alice',
		);

		$stats = $this->service->importV2('alice', ImportService::IMPORT_TYPE_USER_FILE, 'source.csv', 1, null);

		self::assertSame(1, $stats->errorsParsingCount);
		self::assertCount(1, $stats->problems);
		self::assertNull($stats->problemsFile);
	}

	public function testMissingTemporaryReportDoesNotFailImport(): void {
		file_put_contents($this->sourcePath, "Name\ninvalid\n");
		$this->reportAvailable = false;

		$stats = $this->service->importV2('alice', ImportService::IMPORT_TYPE_USER_FILE, 'source.csv', 1, null);

		self::assertSame(1, $stats->errorsParsingCount);
		self::assertCount(1, $stats->problems);
		self::assertNull($stats->problemsFile);
	}
}
