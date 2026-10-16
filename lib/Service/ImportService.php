<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use DateTimeImmutable;
use LogicException;
use OC\User\NoUserException;
use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\BackgroundJob\ImportTableJob;
use OCA\Tables\Db\Column;
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Model\ImportProblem;
use OCA\Tables\Model\ImportProblemsFile;
use OCA\Tables\Model\ImportStats;
use OCA\Tables\Service\ColumnTypes\IColumnTypeBusiness;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Cell\Cell;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Cell\DataType;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\IOFactory;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Shared\Date;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Worksheet\Row;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use SplFileObject;
use TypeError;
use function file_exists;
use function is_string;
use function is_uploaded_file;
use function mb_strlen;
use function mb_substr;
use function preg_match;

class ImportService extends SuperService {

	private readonly IAppData $appData;

	private ?int $tableId = null;
	private ?int $viewId = null;
	/**
	 * @var Column[]
	 */
	private array $columns = [];
	private bool $createUnknownColumns = true;
	private ?int $idColumnIndex = null;
	private int $countMatchingColumns = 0;
	private int $countCreatedColumns = 0;
	private int $countInsertedRows = 0;
	private int $countUpdatedRows = 0;
	private int $countErrors = 0;
	private int $countParsingErrors = 0;
	/**
	 * @var list<ImportProblem>
	 */
	private array $problems = [];

	private array $rawColumnTitles = [];
	private array $rawColumnDataTypes = [];
	private array $columnsConfig = [];

	private ?SplFileObject $problemsFileStream = null;
	private ?ImportProblemsFile $storedProblemsFile = null;
	private readonly IL10N $l10n;

	public const IMPORT_TYPE_USER_FILE = 'user_file';
	public const IMPORT_TYPE_APP_FILE = 'app_file';
	public const IMPORT_TYPE_UPLOADED_FILE = 'uploaded_file';

	private const MAX_ROWS_FOR_IMMEDIATE_IMPORT = 200;
	private const MAX_COLUMNS_FOR_IMMEDIATE_IMPORT = 20;
	private const MAX_PROBLEM_VALUE_LENGTH = 50;
	private const IMPORTS_FOLDER = 'Tables/Imports';

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		private readonly IRootFolder $rootFolder,
		private readonly ColumnService $columnService,
		private readonly RowService $rowService,
		private readonly TableService $tableService,
		private readonly ViewService $viewService,
		private readonly IUserManager $userManager,
		private readonly ColumnsHelper $columnsHelper,
		private readonly IJobList $jobList,
		private readonly ITempManager $tempManager,
		private readonly IFactory $l10nFactory,
		private readonly ActivityManager $activityManager,
		IAppDataFactory $appDataFactory,
		private readonly IFilenameValidator $filenameValidator,
	) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->appData = $appDataFactory->get(Application::APP_ID);
		$this->l10n = $this->l10nFactory->get(Application::APP_ID);
	}

	public function previewImport(?int $tableId, ?int $viewId, string $path): array {
		$this->resetImportState();
		if ($viewId !== null) {
			$this->viewId = $viewId;
		} elseif ($tableId) {
			$this->tableId = $tableId;
		} else {
			$e = new \Exception('Neither tableId nor viewId is given.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		$this->createUnknownColumns = false;
		$previewData = [];

		try {
			$userFolder = $this->rootFolder->getUserFolder($this->userId);
			$error = false;
			if ($userFolder->nodeExists($path)) {
				$file = $userFolder->get($path);
				$tmpFileName = $file->getStorage()->getLocalFile($file->getInternalPath());
				if ($tmpFileName) {
					$spreadsheet = IOFactory::load($tmpFileName);
					$previewData = $this->getPreviewData($spreadsheet->getActiveSheet());
				} else {
					$error = true;
				}
			} elseif (is_uploaded_file($path) && file_exists($path)) {
				$spreadsheet = IOFactory::load($path);
				$previewData = $this->getPreviewData($spreadsheet->getActiveSheet());
			} else {
				$error = true;
			}

			if ($error) {
				throw new NotFoundError('File for import could not be found.');
			}

		} catch (NotFoundException|NotPermittedException|NoUserException|InternalError|PermissionError $e) {
			$this->logger->warning('Storage for user could not be found', ['exception' => $e]);
			throw new NotFoundError('Storage for user could not be found');
		}

		return $previewData;
	}

	/**
	 * Check if import should be done asynchronously based on file size
	 */
	public function shouldImportAsync(string $path): bool {
		if (is_uploaded_file($path) && file_exists($path)) {
			$spreadsheet = IOFactory::load($path);
		} else {
			$userFolder = $this->rootFolder->getUserFolder($this->userId);
			if ($userFolder->nodeExists($path)) {
				$file = $userFolder->get($path);
				$tmpFileName = $file->getStorage()->getLocalFile($file->getInternalPath());
				if ($tmpFileName) {
					$spreadsheet = IOFactory::load($tmpFileName);
				} else {
					throw new NotFoundError('File for import could not be read.');
				}
			} else {
				throw new NotFoundError('File for import could not be found.');
			}
		}

		$worksheet = $spreadsheet->getActiveSheet();

		$highestColumn = $worksheet->getHighestColumn();
		$columnCount = Coordinate::columnIndexFromString($highestColumn);

		// Count rows (excluding header row)
		$rowCount = $worksheet->getHighestRow() - 1;

		return $columnCount * $rowCount > self::MAX_COLUMNS_FOR_IMMEDIATE_IMPORT * self::MAX_ROWS_FOR_IMMEDIATE_IMPORT;
	}

	/**
	 * @param Worksheet $worksheet
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	private function getPreviewData(Worksheet $worksheet): array {
		$firstRow = $worksheet->getRowIterator()->current();
		$secondRow = $worksheet->getRowIterator()->seek(2)->current();

		// Prepare columns data
		$columns = [];
		$this->getColumns($firstRow, $secondRow);

		foreach ($this->rawColumnTitles as $colIndex => $title) {
			if (isset($this->columns[$colIndex]) && $this->columns[$colIndex] !== '') {
				/** @var Column $column */
				$column = $this->columns[$colIndex];
				$columns[] = $column;
			} else {
				$column = [
					'title' => $title,
					'type' => $this->rawColumnDataTypes[$colIndex]['type'],
					'subtype' => $this->rawColumnDataTypes[$colIndex]['subtype'] ?? null,
					'numberDecimals' => $this->rawColumnDataTypes[$colIndex]['number_decimals'] ?? 0,
					'numberPrefix' => $this->rawColumnDataTypes[$colIndex]['number_prefix'] ?? '',
					'numberSuffix' => $this->rawColumnDataTypes[$colIndex]['number_suffix'] ?? '',
				];
				if (mb_strtolower((string)$title) === Column::META_ID_TITLE) {
					$column['id'] = Column::TYPE_META_ID;
				}

				$columns[] = $column;
			}
		}

		// Prepare rows data
		$count = 0;
		$maxCount = 3;
		$rows = [];

		foreach ($worksheet->getRowIterator(2) as $row) {
			$rowData = [];
			$cellIterator = $row->getCellIterator();
			$cellIterator->setIterateOnlyExistingCells(false);

			foreach ($cellIterator as $cell) {
				$value = $cell->getValue();
				// $cellIterator`s index is based on 1, not 0.
				$colIndex = $cellIterator->getCurrentColumnIndex() - 1;
				if (!array_key_exists($colIndex, $this->columns)) {
					continue;
				}

				$column = $this->columns[$colIndex];

				if (!array_key_exists($colIndex, $columns)) {
					continue;
				}

				if (
					($column && $column->getType() === Column::TYPE_DATETIME)
					|| (is_array($columns[$colIndex])
						&& $columns[$colIndex]['type'] === Column::TYPE_DATETIME)
				) {
					if (
						($column && $column->getSubtype() === Column::SUBTYPE_DATETIME_DATE)
						|| (is_array($columns[$colIndex])
							&& $columns[$colIndex]['subtype'] === Column::SUBTYPE_DATETIME_DATE)
					) {
						$format = 'Y-m-d';
					} elseif (
						($column && $column->getSubtype() === Column::SUBTYPE_DATETIME_TIME)
						|| (is_array($columns[$colIndex])
							&& $columns[$colIndex]['subtype'] === Column::SUBTYPE_DATETIME_TIME)
					) {
						$format = 'H:i';
					} else {
						$format = 'Y-m-d H:i';
					}
					$value = $this->parseAndFormatDateTimeString($value, $format);
				} elseif (
					($column && $column->getType() === Column::TYPE_NUMBER
						&& $column->getNumberSuffix() === '%')
					|| (is_array($columns[$colIndex])
						&& $columns[$colIndex]['type'] === Column::TYPE_NUMBER
						&& $columns[$colIndex]['numberSuffix'] === '%')
				) {
					$value = $value * 100;
				} elseif (
					($column && $column->getType() === Column::TYPE_SELECTION
						&& $column->getSubtype() === Column::SUBTYPE_SELECTION_CHECK)
					|| (is_array($columns[$colIndex])
						&& $columns[$colIndex]['type'] === Column::TYPE_SELECTION
						&& $columns[$colIndex]['subtype'] === Column::SUBTYPE_SELECTION_CHECK)
				) {
					$value = in_array($cell->getFormattedValue(), ['TRUE', '1'], true) ? 'true' : 'false';
				}

				$rowData[] = $value;
			}

			$rows[] = $rowData;
			$count++;

			if ($count >= $maxCount) {
				break;
			}
		}

		return [
			'columns' => $columns,
			'rows' => $rows,
		];
	}

	/**
	 * @deprecated Use {@link scheduleImport} and {@link importV2} instead.
	 * @param ?int $tableId
	 * @param ?int $viewId
	 * @param string $path
	 * @param bool $createMissingColumns
	 * @return array
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function import(?int $tableId, ?int $viewId, string $path, bool $createMissingColumns = true, array $columnsConfig = []): array {
		$this->resetImportState();
		if ($viewId !== null) {
			$view = $this->viewService->find($viewId);
			if (!$this->permissionsService->canCreateRows($view)) {
				throw new PermissionError('create row at the view id = ' . $viewId . ' is not allowed.');
			}
			if ($createMissingColumns && !$this->permissionsService->canManageTableById($view->getTableId())) {
				throw new PermissionError('create columns at the view id = ' . $viewId . ' is not allowed.');
			}
			$this->viewId = $viewId;
		}
		if ($tableId) {
			$table = $this->tableService->find($tableId);
			if (!$this->permissionsService->canCreateRows($table, 'table')) {
				throw new PermissionError('create row at the view id = ' . (string)$viewId . ' is not allowed.');
			}
			if ($createMissingColumns && !$this->permissionsService->canManageTable($table)) {
				throw new PermissionError('create columns at the view id = ' . (string)$viewId . ' is not allowed.');
			}
			$this->tableId = $tableId;
		}
		if (!$this->tableId && !$this->viewId) {
			$e = new \Exception('Neither tableId nor viewId is given.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		if ($this->tableId && $this->viewId) {
			$e = new LogicException('Both table ID and view ID are provided, but only one of them is allowed');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		if ($this->userId === null || $this->userManager->get($this->userId) === null) {
			$error = 'No user in context, can not import data. Cancel.';
			$this->logger->debug($error);
			throw new InternalError($error);
		}

		$this->createUnknownColumns = $createMissingColumns;
		$this->columnsConfig = $columnsConfig;

		try {
			$userFolder = $this->rootFolder->getUserFolder($this->userId);
			$error = false;
			if ($userFolder->nodeExists($path)) {
				$file = $userFolder->get($path);
				$tmpFileName = $file->getStorage()->getLocalFile($file->getInternalPath());
				if ($tmpFileName) {
					$spreadsheet = IOFactory::load($tmpFileName);
					$this->loop($spreadsheet->getActiveSheet());
				} else {
					$error = true;
				}
			} elseif (is_uploaded_file($path) && file_exists($path)) {
				$spreadsheet = IOFactory::load($path);
				$this->loop($spreadsheet->getActiveSheet());
			} else {
				$error = true;
			}
			if ($error) {
				throw new NotFoundError('File for import could not be found.');
			}

		} catch (NotFoundException|NotPermittedException|NoUserException|InternalError|PermissionError $e) {
			$this->logger->warning('Storage for user could not be found', ['exception' => $e]);
			throw new NotFoundError('Storage for user could not be found');
		}

		return [
			'found_columns_count' => count(array_filter($this->columns, fn ($column): bool => $column instanceof Column)),
			'matching_columns_count' => $this->countMatchingColumns,
			'created_columns_count' => $this->countCreatedColumns,
			'inserted_rows_count' => $this->countInsertedRows,
			'updated_rows_count' => $this->countUpdatedRows,
			'errors_parsing_count' => $this->countParsingErrors,
			'errors_count' => $this->countErrors,
		];
	}

	/**
	 * @param ?int $tableId
	 * @param ?int $viewId
	 * @param string $path
	 * @param bool $createMissingColumns
	 * @param array $columnsConfig
	 * @param ?string $originalFileName Original client side name of an uploaded file
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function scheduleImport(?int $tableId, ?int $viewId, string $path, bool $createMissingColumns = true, array $columnsConfig = [], ?string $originalFileName = null) {
		if ($viewId !== null) {
			$view = $this->viewService->find($viewId);
			if (!$this->permissionsService->canCreateRows($view)) {
				throw new PermissionError('create row at the view id = ' . $viewId . ' is not allowed.');
			}
			if ($createMissingColumns && !$this->permissionsService->canManageTableById($view->getTableId())) {
				throw new PermissionError('create columns at the view id = ' . $viewId . ' is not allowed.');
			}
			$this->viewId = $viewId;
		}
		if ($tableId) {
			$table = $this->tableService->find($tableId);
			if (!$this->permissionsService->canCreateRows($table, 'table')) {
				throw new PermissionError('create row at the view id = ' . (string)$viewId . ' is not allowed.');
			}
			if ($createMissingColumns && !$this->permissionsService->canManageTable($table)) {
				throw new PermissionError('create columns at the view id = ' . (string)$viewId . ' is not allowed.');
			}
			$this->tableId = $tableId;
		}
		if (!$this->tableId && !$this->viewId) {
			$e = new \Exception('Neither tableId nor viewId is given.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		if ($this->tableId && $this->viewId) {
			$e = new \LogicException('Both table ID and view ID are provided, but only one of them is allowed');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		if ($this->userId === null || $this->userManager->get($this->userId) === null) {
			$error = 'No user in context, can not import data. Cancel.';
			$this->logger->debug($error);
			throw new InternalError($error);
		}

		$importFileName = null;
		// uploaded file: copy it into app data so the background job
		// can read it later without adding it to the user's files
		if (\file_exists($path)) {
			if ($originalFileName === null || !$this->filenameValidator->isFilenameValid($originalFileName)) {
				$originalFileName = 'import-' . bin2hex(random_bytes(8));
			}
			$extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
			$importFileName = 'import-' . bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
			$this->getImportAppDataDir()->newFile($importFileName)->putContent(\file_get_contents($path));
		}

		$this->jobList->add(
			ImportTableJob::class,
			[
				'user_id' => $this->userId,
				'table_id' => $this->tableId,
				'view_id' => $this->viewId,
				'user_file_path' => $importFileName === null ? $path : null,
				'import_file_name' => $importFileName,
				'original_file_name' => $originalFileName,
				'create_missing_columns' => $createMissingColumns,
				'columns_config' => $columnsConfig,
			]
		);
	}

	/**
	 * @param string $userId
	 * @param ?int $tableId
	 * @param ?int $viewId
	 * @param self::IMPORT_TYPE_* $importType
	 * @param string $path Path to a file in the user's storage, if the file is not uploaded
	 *                     or Name of the file in the app data directory, if the file is uploaded
	 *                     or Path to uploaded file
	 * @param bool $createMissingColumns
	 * @param array $columnsConfig
	 * @param ?string $originalFileName Original client side name of an uploaded file, used for the issue report name
	 * @return ImportStats
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 */
	public function importV2(string $userId, string $importType, string $path, ?int $tableId, ?int $viewId, bool $createMissingColumns = true, array $columnsConfig = [], ?string $originalFileName = null): ImportStats {
		$this->resetImportState();
		$this->openProblemsFileStream();
		$this->userId = $userId;
		$this->tableId = $tableId;
		$this->viewId = $viewId;
		$this->createUnknownColumns = $createMissingColumns;
		$this->columnsConfig = $columnsConfig;

		try {
			if ($importType === self::IMPORT_TYPE_USER_FILE) {
				$file = $this->rootFolder->getUserFolder($this->userId)->get($path);
				$temporaryFile = $file->getStorage()->getLocalFile($file->getInternalPath());
				$spreadsheet = IOFactory::load($temporaryFile);
				$this->loop($spreadsheet->getActiveSheet());
			} elseif ($importType === self::IMPORT_TYPE_UPLOADED_FILE) {
				if (!is_uploaded_file($path) || !file_exists($path)) {
					throw new NotFoundError('Uploaded file for import not found.');
				}
				$spreadsheet = IOFactory::load($path);
				$this->loop($spreadsheet->getActiveSheet());
			} elseif ($importType === self::IMPORT_TYPE_APP_FILE) {
				$file = $this->getImportAppDataDir()->getFile($path);
				$temporaryFile = $this->tempManager->getTemporaryFile('.' . $file->getExtension());
				file_put_contents($temporaryFile, $file->getContent());
				$spreadsheet = IOFactory::load($temporaryFile);
				$this->loop($spreadsheet->getActiveSheet());
			} else {
				throw new NotFoundError('No file for import given.');
			}
		} catch (NotFoundException|NotPermittedException|NoUserException|InternalError|PermissionError $e) {
			$this->logger->warning('Storage for user could not be found', ['exception' => $e]);
			throw new NotFoundError('Storage for user could not be found', 0, $e);
		}

		$stats = new ImportStats(
			count(array_filter($this->columns, fn ($column): bool => $column instanceof Column)),
			$this->countMatchingColumns,
			$this->countCreatedColumns,
			$this->countInsertedRows,
			$this->countUpdatedRows,
			$this->countParsingErrors,
			$this->countErrors,
			$this->problems
		);
		$this->finishImport($userId, $importType === self::IMPORT_TYPE_USER_FILE ? $path : $originalFileName, $stats);
		return $stats;
	}

	/**
	 * Store the collected issue report and publish the import-finished
	 * activity entry. Reporting failures must not fail the import itself.
	 */
	private function finishImport(string $userId, ?string $sourcePath, ImportStats $stats): void {
		if ($this->problemsFileStream !== null) {
			try {
				$this->storedProblemsFile = $this->storeProblemsFile($userId, $sourcePath);
				$stats->problemsFile = $this->storedProblemsFile;
			} catch (\Throwable $e) {
				$this->logger->warning('Could not store the import problems file: ' . $e->getMessage(), ['exception' => $e]);
			}
		}

		try {
			if ($this->viewId !== null) {
				$element = $this->tableService->find($this->viewService->find($this->viewId, true, $userId)->getTableId(), true, $userId);
			} elseif ($this->tableId !== null) {
				$element = $this->tableService->find($this->tableId, true, $userId);
			} else {
				return;
			}
			$this->activityManager->triggerEvent(
				objectType: ActivityManager::TABLES_OBJECT_TABLE,
				object: $element,
				subject: ActivityManager::SUBJECT_IMPORT_FINISHED,
				additionalParams: [
					'importStats' => $stats->withoutProblems(),
					'importProblemsFile' => $this->storedProblemsFile,
				],
				author: $userId,
			);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not create the import activity entry: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	/**
	 * File information of the problems file stored by the last import, if any.
	 * Used by the background job to link the file in notifications.
	 */
	public function getStoredProblemsFile(): ?ImportProblemsFile {
		return $this->storedProblemsFile;
	}

	private function resetImportState(): void {
		$this->tableId = null;
		$this->viewId = null;
		$this->columns = [];
		$this->createUnknownColumns = true;
		$this->idColumnIndex = null;
		$this->countMatchingColumns = 0;
		$this->countCreatedColumns = 0;
		$this->countInsertedRows = 0;
		$this->countUpdatedRows = 0;
		$this->countErrors = 0;
		$this->countParsingErrors = 0;
		$this->problems = [];
		$this->rawColumnTitles = [];
		$this->rawColumnDataTypes = [];
		$this->columnsConfig = [];
		$this->problemsFileStream = null;
		$this->storedProblemsFile = null;
	}

	private function openProblemsFileStream(): void {
		$tempPath = $this->tempManager->getTemporaryFile('.csv');
		if ($tempPath === false) {
			$this->logger->warning('Could not create a temporary file for the import problems file');
			return;
		}
		$this->problemsFileStream = new SplFileObject($tempPath, 'w+');
		// UTF-8 BOM so spreadsheet apps detect the encoding
		$this->problemsFileStream->fwrite("\xEF\xBB\xBF");
		$this->problemsFileStream->fputcsv([
			$this->l10n->t('Row'),
			$this->l10n->t('Column'),
			$this->l10n->t('Code'),
			$this->l10n->t('Value'),
			$this->l10n->t('Problem'),
		]);
	}

	/**
	 * Persist the collected problems file to the user's storage.
	 * Returns file information for activity/notification parameters or null
	 * when no problems were recorded.
	 */
	public function storeProblemsFile(string $userId, ?string $sourcePath): ?ImportProblemsFile {
		if ($this->problemsFileStream === null || $this->problems === []) {
			return null;
		}

		$importsFolder = $this->getImportsFolder($userId);
		if ($importsFolder === null) {
			return null;
		}

		$baseName = $sourcePath !== null ? (pathinfo($sourcePath, PATHINFO_FILENAME) ?: 'import') : 'import';

		try {
			$problemsFileName = $baseName . '.import-problems-' . date('Ymd-His') . '.csv';
			if (!$this->filenameValidator->isFilenameValid($problemsFileName)) {
				$problemsFileName = 'import-problems-' . bin2hex(random_bytes(8)) . '.csv';
			}
			$problemsFileName = $importsFolder->getNonExistingName($problemsFileName);
			$problemsFile = $importsFolder->newFile($problemsFileName);
			$this->problemsFileStream->rewind();
			$problemsFile->putContent($this->problemsFileStream->fread($this->problemsFileStream->getSize()));
		} catch (NotPermittedException $e) {
			$this->logger->warning('Could not store the import problems file', ['exception' => $e]);
			return null;
		}

		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$relativePath = $userFolder->getRelativePath($problemsFile->getPath());
		} catch (NotPermittedException|NoUserException) {
			$relativePath = null;
		}

		return new ImportProblemsFile(
			$problemsFile->getId(),
			$problemsFile->getName(),
			ltrim($relativePath ?? '', '/'),
		);
	}

	/**
	 * Get (and lazily create) the folder used for generated import
	 * issue reports in the user's storage.
	 */
	private function getImportsFolder(string $userId): ?Folder {
		try {
			$dir = $this->rootFolder->getUserFolder($userId);
		} catch (NotPermittedException|NoUserException $e) {
			$this->logger->warning('Could not open user folder for the import', ['exception' => $e]);
			return null;
		}

		foreach (explode('/', self::IMPORTS_FOLDER) as $part) {
			try {
				$child = $dir->get($part);
			} catch (NotFoundException) {
				try {
					$child = $dir->newFolder($part);
				} catch (NotPermittedException $e) {
					$this->logger->warning('Could not create the imports folder', ['exception' => $e]);
					return null;
				}
			}
			if (!$child instanceof Folder) {
				$this->logger->warning('Imports folder path is occupied by a file: ' . $child->getPath());
				return null;
			}
			$dir = $child;
		}

		return $dir;
	}

	private function addProblem(string $code, ?int $row = null, ?string $column = null, ?string $value = null): void {
		if ($value !== null && mb_strlen($value) > self::MAX_PROBLEM_VALUE_LENGTH) {
			$value = mb_substr($value, 0, self::MAX_PROBLEM_VALUE_LENGTH) . '…';
		}
		$this->problemsFileStream?->fputcsv([
			$row,
			$column,
			$code,
			$value,
			ImportProblem::describeCode($this->l10n, $code),
		]);
		$this->problems[] = new ImportProblem($code, $row, $column, $value, ImportProblem::describeProblem($this->l10n, $code, $value));
	}

	/**
	 * @param Worksheet $worksheet
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	private function loop(Worksheet $worksheet): void {
		$rowIterator = $worksheet->getRowIterator();
		$firstRow = $rowIterator->current();
		$rowIterator->next();
		if (!$rowIterator->valid()) {
			return;
		}
		$secondRow = $rowIterator->current();
		unset($rowIterator);
		$this->getColumns($firstRow, $secondRow);

		if (empty(array_filter($this->columns))) {
			$this->addProblem(ImportProblem::CODE_NO_COLUMNS_FOUND);
			return;
		}

		$columnBusinesses = [];
		foreach ($this->columns as $column) {
			$columnBusinesses[$column->getId()] = $this->columnsHelper->getColumnBusinessObject($column);
		}

		foreach ($worksheet->getRowIterator(2) as $row) {
			// parse row data
			$this->upsertRow($row, $columnBusinesses);
		}
	}

	/*
	 * @return string Stringified value
	 */
	/**
	 * @return false|string
	 */
	private function parseValueByColumnType(string $value, Column $column, IColumnTypeBusiness $columnBusiness, int $rowIndex): string|false {
		try {
			if (!$columnBusiness->canBeParsedDisplayValue($value, $column)) {
				$this->logger->warning('Value ' . $value . ' could not be parsed for column ' . $column->getTitle());
				$this->countParsingErrors++;
				$this->addProblem(ImportProblem::CODE_PARSE_FAILED, $rowIndex, $column->getTitle(), $value);
				return '';
			}
			return $columnBusiness->parseDisplayValue($value, $column);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->error('Column type business class not found', ['exception' => $e]);
		}
		return '';
	}

	/**
	 * @param Row $row
	 * @param array<int, IColumnTypeBusiness> $columnBusinesses Indexed by columnId
	 * @return void
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 */
	private function upsertRow(Row $row, array $columnBusinesses): void {
		$cellIterator = $row->getCellIterator();
		$cellIterator->setIterateOnlyExistingCells(false);

		$rowId = null;
		try {
			$i = -1;
			$data = [];
			$hasData = false;
			foreach ($cellIterator as $cell) {
				$i++;

				if ($this->idColumnIndex !== null && $i === $this->idColumnIndex) {
					// if this is the ID column, we need to get the ID from the cell
					if ($cell && $cell->getValue() !== null) {
						$rowId = $cell->getValue();
					}
					if ($rowId !== null && !is_numeric($rowId)) {
						$this->logger->warning('ID column value is not numeric: ' . $rowId);
						$this->countErrors++;
						$this->addProblem(ImportProblem::CODE_ID_NOT_NUMERIC, $row->getRowIndex(), null, (string)$rowId);
						return;
					}
					$rowId = (int)$rowId;
					continue;
				}

				// only add the dataset if column is known
				if (!isset($this->columns[$i]) || $this->columns[$i] === '') {
					$this->logger->debug('Column unknown while fetching rows data for importing.');
					continue;
				}

				/** @var Column $column */
				$column = $this->columns[$i];

				// if cell is empty
				if (!$cell || $cell->getValue() === null) {
					$this->logger->info('Cell is empty while fetching rows data for importing.');
					if ($column->getMandatory()) {
						$this->logger->warning('Mandatory column "' . $column->getTitle() . '" was not set');
						$this->countErrors++;
						$this->addProblem(ImportProblem::CODE_MISSING_MANDATORY, $row->getRowIndex(), $column->getTitle());
						return;
					}
					continue;
				}

				$value = $cell->getValue();
				$hasData = $hasData || !empty($value);

				if ($column->getType() === Column::TYPE_DATETIME) {
					if ($column->getSubtype() === Column::SUBTYPE_DATETIME_DATE) {
						$format = 'Y-m-d';
					} elseif ($column->getSubtype() === Column::SUBTYPE_DATETIME_TIME) {
						$format = 'H:i';
					} else {
						$format = 'Y-m-d H:i';
					}
					$value = $this->parseAndFormatDateTimeString($value, $format);
				} elseif ($column->getType() === Column::TYPE_NUMBER
					&& $column->getNumberSuffix() === '%'
				) {
					$value = $value * 100;
				} elseif ($column->getType() === Column::TYPE_SELECTION
					&& $column->getSubtype() === Column::SUBTYPE_SELECTION_CHECK
				) {
					$value = in_array($cell->getFormattedValue(), ['TRUE', '1'], true) ? 'true' : 'false';
				}

				$data[] = [
					'columnId' => $column->getId(),
					'value' => json_decode($this->parseValueByColumnType($value, $column, $columnBusinesses[$column->getId()], $row->getRowIndex())),
				];
			}

			if (!$hasData) {
				$this->logger->debug('Skipped empty row ' . $row->getRowIndex() . ' during import');
				return;
			}

			if ($rowId) {
				$this->rowService->updateSet($rowId, $this->viewId, $data, $this->userId, $this->tableId);
				$this->countUpdatedRows++;
			} else {
				$this->rowService->create($this->tableId, $this->viewId, $data, $this->userId);
				$this->countInsertedRows++;
			}
		} catch (PermissionError $e) {
			$this->logger->error('Could not create/update row while importing, no permission.', ['exception' => $e]);
			$this->countErrors++;
			$this->addProblem(ImportProblem::CODE_PERMISSION_DENIED, $row->getRowIndex());
		} catch (BadRequestError|InternalError $e) {
			$this->logger->error('Error while creating/updating  new row for import.', ['exception' => $e]);
			$this->countErrors++;
			$this->addProblem($rowId ? ImportProblem::CODE_ROW_UPDATE_FAILED : ImportProblem::CODE_ROW_INSERT_FAILED, $row->getRowIndex());
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage(), 0, $e);
		} catch (\Throwable $e) {
			$this->countErrors++;
			$this->addProblem($rowId ? ImportProblem::CODE_ROW_UPDATE_FAILED : ImportProblem::CODE_ROW_INSERT_FAILED, $row->getRowIndex());
			$this->logger->error('Error while creating/updating new row for import.', ['exception' => $e]);
		}
	}

	/**
	 * @param null|string $value
	 */
	private function valueToDateTimeImmutable(?string $value): ?DateTimeImmutable {
		if (
			$value === false
			|| $value === null
			|| (is_string($value)
				&& mb_strlen($value) < 3 // Let pass potential 3-letter month names
				&& preg_match('/\d/', $value) !== 1) // or anything containing a digit
		) {
			return null;
		}
		try {
			$dt = Date::excelToDateTimeObject($value);
			Date::roundMicroseconds($dt);
			return DateTimeImmutable::createFromMutable($dt);
		} catch (TypeError) {
			try {
				return (new DateTimeImmutable($value));
			} catch (\Exception $e) {
				$this->logger->debug('Could not parse string {value} as date time.', [
					'exception' => $e,
					'value' => $value,
				]);
				return null;
			}
		}
	}

	private function parseAndFormatDateTimeString(?string $value, string $format): string {
		$dateTime = $this->valueToDateTimeImmutable($value);
		return $dateTime?->format($format) ?: '';
	}

	/**
	 * @param Row $firstRow
	 * @param Row $secondRow
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function getColumns(Row $firstRow, Row $secondRow): void {
		$cellIterator = $firstRow->getCellIterator();
		$secondRowCellIterator = $secondRow->getCellIterator();
		$titles = [];
		$dataTypes = [];
		$rawColumnTitles = [];
		$rawColumnDataTypes = [];
		$columnFileIndices = [];
		$index = 0;
		$countMatchingColumnsFromConfig = 0;
		$countCreatedColumnsFromConfig = 0;
		$lastCellWasEmpty = false;
		$hasGapInTitles = false;
		$this->columns = [];

		foreach ($cellIterator as $cell) {
			if ($cell && $cell->getValue() !== null && $cell->getValue() !== '') {
				$title = $cell->getValue();
				$titleRaw = $title;
				$dataType = $this->parseColumnDataType($secondRowCellIterator->current());
				$shouldImport = true;

				if (!$this->columnsConfig && mb_strtolower((string)$title) === Column::META_ID_TITLE) {
					$this->idColumnIndex = $index;
				} elseif (isset($this->columnsConfig[$index])) {
					if ($this->columnsConfig[$index]['action'] === 'ignore') {
						$shouldImport = false;
					} elseif (
						$this->columnsConfig[$index]['action'] === 'exist'
						&& isset($this->columnsConfig[$index]['existColumn'])
						&& $this->columnsConfig[$index]['existColumn']['id'] === Column::TYPE_META_ID
					) {
						$this->idColumnIndex = $index;
						$countMatchingColumnsFromConfig++;
						$shouldImport = false;
					} elseif (
						$this->columnsConfig[$index]['action'] === 'exist'
						&& $this->columnsConfig[$index]['existColumn']
					) {
						$title = $this->columnsConfig[$index]['existColumn']['label'];
						$countMatchingColumnsFromConfig++;
					} elseif (
						$this->columnsConfig[$index]['action'] === 'new'
						&& $this->createUnknownColumns
					) {
						$column = $this->columnService->create(
							$this->userId,
							$this->tableId,
							$this->viewId,
							ColumnDto::createFromArray($this->columnsConfig[$index]),
							$this->columnsConfig[$index]['selectedViewIds'] ?? []
						);
						$title = $column->getTitle();
						$countCreatedColumnsFromConfig++;
					}
				}

				$rawColumnTitles[] = $titleRaw;
				$rawColumnDataTypes[] = $dataType;

				if ($shouldImport) {
					$titles[] = $title;
					$dataTypes[] = $dataType;
					$columnFileIndices[] = $index;
				}

				if ($lastCellWasEmpty) {
					$hasGapInTitles = true;
				}
				$lastCellWasEmpty = false;
			} else {
				$this->logger->debug('No cell given or cellValue is empty while loading columns for importing');
				if ($cell && $cell->getDataType() === 'null') {
					// LibreOffice generated XLSX doc may have more empty columns in the first row.
					// Continue without increasing error count, but leave a marker to detect gaps in titles.
					$lastCellWasEmpty = true;
					continue;
				}
				$this->countErrors++;
				$this->addProblem(ImportProblem::CODE_EMPTY_COLUMN_TITLE, 1, $cell ? $cell->getColumn() : Coordinate::stringFromColumnIndex($index + 1));
			}
			$secondRowCellIterator->next();
			$index++;
		}

		if ($hasGapInTitles) {
			$this->logger->info('Imported table is having a gap in column titles');
			$this->countErrors++;
			$this->addProblem(ImportProblem::CODE_COLUMN_TITLE_GAP, 1);
		}

		$this->rawColumnTitles = $rawColumnTitles;
		$this->rawColumnDataTypes = $rawColumnDataTypes;

		try {
			$result = $this->columnService->findOrCreateColumnsByTitleForTableAsArray($this->tableId, $this->viewId, $titles, $dataTypes, $this->userId, $this->createUnknownColumns, $this->countCreatedColumns, $this->countMatchingColumns);
			foreach ($result as $resultIndex => $column) {
				$this->columns[$columnFileIndices[$resultIndex]] = $column;
			}
			if ($this->idColumnIndex !== null && (!isset($this->columns[$this->idColumnIndex]) || !$this->columns[$this->idColumnIndex] instanceof Column)) {
				$this->countMatchingColumns++;
			}
			if (!empty($this->columnsConfig)) {
				$this->countMatchingColumns = $countMatchingColumnsFromConfig;
				$this->countCreatedColumns = $countCreatedColumnsFromConfig;
			}
		} catch (Exception $e) {
			throw new InternalError($e->getMessage());
		}
	}

	private function parseColumnDataType(Cell $cell): array {
		$originDataType = $cell->getDataType();
		$value = $cell->getValue();
		$formattedValue = $cell->getFormattedValue();
		$dataType = [
			'type' => Column::TYPE_TEXT,
			'subtype' => Column::SUBTYPE_TEXT_LINE,
		];

		if (!is_numeric($formattedValue)
			&& ($this->valueToDateTimeImmutable($value) instanceof DateTimeImmutable
				|| Date::isDateTime($cell)
				|| $originDataType === DataType::TYPE_ISO_DATE)
		) {
			// the formatted value stems from the office document and shows the original user intent
			$dateAnalysis = date_parse($formattedValue);
			$containsDate = $dateAnalysis['year'] !== false || $dateAnalysis['month'] !== false || $dateAnalysis['day'] !== false;
			$containsTime = $dateAnalysis['hour'] !== false || $dateAnalysis['minute'] !== false || $dateAnalysis['second'] !== false;

			if ($containsDate && !$containsTime) {
				$subType = Column::SUBTYPE_DATETIME_DATE;
			} elseif (!$containsDate && $containsTime) {
				$subType = Column::SUBTYPE_DATETIME_TIME;
			} else {
				$subType = '';
			}

			$dataType = [
				'type' => Column::TYPE_DATETIME,
				'subtype' => $subType,
			];
		} elseif ($originDataType === DataType::TYPE_NUMERIC) {
			if (str_contains($formattedValue, '%')) {
				$dataType = [
					'type' => Column::TYPE_NUMBER,
					'number_decimals' => 2,
					'number_suffix' => '%',
				];
			} elseif (str_contains($formattedValue, '€')) {
				$dataType = [
					'type' => Column::TYPE_NUMBER,
					'number_decimals' => 2,
					'number_suffix' => '€',
				];
			} elseif (str_contains($formattedValue, 'EUR')) {
				$dataType = [
					'type' => Column::TYPE_NUMBER,
					'number_decimals' => 2,
					'number_suffix' => 'EUR',
				];
			} elseif (str_contains($formattedValue, '$')) {
				$dataType = [
					'type' => Column::TYPE_NUMBER,
					'number_decimals' => 2,
					'number_prefix' => '$',
				];
			} elseif (str_contains($formattedValue, 'USD')) {
				$dataType = [
					'type' => Column::TYPE_NUMBER,
					'number_decimals' => 2,
					'number_suffix' => 'USD',
				];
			} elseif (is_float($value)) {
				$decimals = strlen(substr(strrchr((string)$value, '.'), 1));
				$dataType = [
					'type' => Column::TYPE_NUMBER,
					'number_decimals' => $decimals,
				];
			} else {
				$dataType = [
					'type' => Column::TYPE_NUMBER,
				];
			}
		} elseif ($originDataType === DataType::TYPE_BOOL
			|| ($originDataType === DataType::TYPE_FORMULA
				&& in_array($formattedValue, ['FALSE', 'TRUE', '', '1'], true))
		) {
			$dataType = [
				'type' => Column::TYPE_SELECTION,
				'subtype' => Column::SUBTYPE_SELECTION_CHECK,
				'selection_default' => 'false',
			];
		}

		return $dataType;
	}

	/**
	 * @return ISimpleFolder
	 * @throws \OCP\Files\NotPermittedException
	 */
	private function getImportAppDataDir(): ISimpleFolder {
		try {
			return $this->appData->getFolder('import');
		} catch (NotFoundException) {
			return $this->appData->newFolder('import');
		}
	}
}
