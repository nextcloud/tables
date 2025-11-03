<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use DateTimeImmutable;
use LogicException;
use OC\User\NoUserException;
use OCA\Tables\Db\Column;
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnTypes\IColumnTypeBusiness;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Cell\Cell;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Cell\DataType;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\IOFactory;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Shared\Date;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Worksheet\Row;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IUserManager;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use TypeError;
use function file_exists;
use function is_string;
use function is_uploaded_file;
use function mb_strlen;
use function preg_match;

class ImportService extends SuperService {

	private IRootFolder $rootFolder;
	private ColumnService $columnService;
	private RowService $rowService;
	private TableService $tableService;
	private ViewService $viewService;
	private IUserManager $userManager;

	private ?int $tableId = null;
	private ?int $viewId = null;
	private array $columns = [];
	private bool $createUnknownColumns = true;
	private ?int $idColumnIndex = null;
	private int $countMatchingColumns = 0;
	private int $countCreatedColumns = 0;
	private int $countInsertedRows = 0;
	private int $countUpdatedRows = 0;
	private int $countErrors = 0;
	private int $countParsingErrors = 0;

	private array $rawColumnTitles = [];
	private array $rawColumnDataTypes = [];
	private array $columnsConfig = [];

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		IRootFolder $rootFolder,
		ColumnService $columnService,
		RowService $rowService,
		TableService $tableService,
		ViewService $viewService,
		IUserManager $userManager,
	) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->rootFolder = $rootFolder;
		$this->columnService = $columnService;
		$this->rowService = $rowService;
		$this->tableService = $tableService;
		$this->viewService = $viewService;
		$this->userManager = $userManager;
	}

	public function previewImport(?int $tableId, ?int $viewId, string $path): array {
		if ($viewId !== null) {
			$this->viewId = $viewId;
		} elseif ($tableId) {
			$this->tableId = $tableId;
		} else {
			$e = new \Exception('Neither tableId nor viewId is given.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
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
			if ($this->columns[$colIndex] !== '') {
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
				if (mb_strtolower($title) === Column::META_ID_TITLE) {
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
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		if ($this->tableId && $this->viewId) {
			$e = new LogicException('Both table ID and view ID are provided, but only one of them is allowed');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
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
			'found_columns_count' => count($this->columns),
			'matching_columns_count' => $this->countMatchingColumns,
			'created_columns_count' => $this->countCreatedColumns,
			'inserted_rows_count' => $this->countInsertedRows,
			'updated_rows_count' => $this->countUpdatedRows,
			'errors_parsing_count' => $this->countParsingErrors,
			'errors_count' => $this->countErrors,
		];
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
			return;
		}

		foreach ($worksheet->getRowIterator(2) as $row) {
			// parse row data
			$this->upsertRow($row);
		}
	}

	/*
	 * @return stringify value
	 */
	private function parseValueByColumnType(string $value, Column $column): string {
		try {
			$businessClassName = 'OCA\Tables\Service\ColumnTypes\\';
			$businessClassName .= ucfirst($column->getType()) . ucfirst($column->getSubtype()) . 'Business';
			/** @var IColumnTypeBusiness $columnBusiness */
			$columnBusiness = Server::get($businessClassName);
			if (!$columnBusiness->canBeParsedDisplayValue($value, $column)) {
				$this->logger->warning('Value ' . $value . ' could not be parsed for column ' . $column->getTitle());
				$this->countParsingErrors++;
				return '';
			}
			return $columnBusiness->parseDisplayValue($value, $column);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->debug('Column type business class not found', ['exception' => $e]);
		}
		return '';
	}

	/**
	 * @param Row $row
	 * @return void
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 */
	private function upsertRow(Row $row): void {
		$cellIterator = $row->getCellIterator();
		$cellIterator->setIterateOnlyExistingCells(false);

		try {
			$i = -1;
			$data = [];
			$hasData = false;
			$id = null;
			foreach ($cellIterator as $cell) {
				$i++;

				if ($this->idColumnIndex !== null && $i === $this->idColumnIndex) {
					// if this is the ID column, we need to get the ID from the cell
					if ($cell && $cell->getValue() !== null) {
						$id = $cell->getValue();
					}
					if ($id !== null && !is_numeric($id)) {
						$this->logger->warning('ID column value is not numeric: ' . $id);
						$this->countErrors++;
						return;
					}
					$id = (int)$id;
					continue;
				}

				$columnKey = $i;
				if ($this->columnsConfig && $this->idColumnIndex !== null && $i > $this->idColumnIndex) {
					// if we have an ID column, we need to adjust the index
					$columnKey = $i - 1;
				}

				// only add the dataset if column is known
				if (!isset($this->columns[$columnKey]) || $this->columns[$columnKey] === '') {
					$this->logger->debug('Column unknown while fetching rows data for importing.');
					continue;
				}

				/** @var Column $column */
				$column = $this->columns[$columnKey];

				// if cell is empty
				if (!$cell || $cell->getValue() === null) {
					$this->logger->info('Cell is empty while fetching rows data for importing.');
					if ($column->getMandatory()) {
						$this->logger->warning('Mandatory column was not set');
						$this->countErrors++;
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
					'value' => json_decode($this->parseValueByColumnType($value, $column)),
				];
			}

			if (!$hasData) {
				$this->logger->debug('Skipped empty row ' . $row->getRowIndex() . ' during import');
				return;
			}

			if ($id) {
				$this->rowService->updateSet($id, $this->viewId, $data, $this->userId, $this->tableId);
				$this->countUpdatedRows++;
			} else {
				$this->rowService->create($this->tableId, $this->viewId, $data);
				$this->countInsertedRows++;
			}
		} catch (PermissionError $e) {
			$this->logger->error('Could not create/update row while importing, no permission.', ['exception' => $e]);
			$this->countErrors++;
		} catch (BadRequestError|InternalError $e) {
			$this->logger->error('Error while creating/updating  new row for import.', ['exception' => $e]);
			$this->countErrors++;
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage(), 0, $e);
		} catch (\Throwable $e) {
			$this->countErrors++;
			$this->logger->error('Error while creating/updating new row for import.', ['exception' => $e]);
		}
	}

	private function valueToDateTimeImmutable(mixed $value): ?DateTimeImmutable {
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
		$index = 0;
		$countMatchingColumnsFromConfig = 0;
		$countCreatedColumnsFromConfig = 0;
		$lastCellWasEmpty = false;
		$hasGapInTitles = false;
		foreach ($cellIterator as $cell) {
			if ($cell && $cell->getValue() !== null && $cell->getValue() !== '') {
				$title = $cell->getValue();

				if (!$this->columnsConfig && mb_strtolower($title) === Column::META_ID_TITLE) {
					$this->idColumnIndex = $index;
					$titles[] = $title;
					$dataTypes[] = $this->parseColumnDataType($secondRowCellIterator->current());
					$secondRowCellIterator->next();
					$index++;
					continue;
				}
				if (isset($this->columnsConfig[$index]) && $this->columnsConfig[$index]['action'] === 'exist' && $this->columnsConfig[$index]['existColumn']) {
					$title = $this->columnsConfig[$index]['existColumn']['label'];
					$countMatchingColumnsFromConfig++;

					// no need to create the ID (Meta) column as it used for update
					if ($this->columnsConfig[$index]['existColumn']['id'] === Column::TYPE_META_ID) {
						$this->idColumnIndex = $index;
						$secondRowCellIterator->next();
						$index++;
						continue;
					}
				}
				if (isset($this->columnsConfig[$index]) && $this->columnsConfig[$index]['action'] === 'new' && $this->createUnknownColumns) {
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
				$titles[] = $title;

				// Convert data type to our data type
				$dataTypes[] = $this->parseColumnDataType($secondRowCellIterator->current());
				if ($lastCellWasEmpty) {
					$hasGapInTitles = true;
				}
				$lastCellWasEmpty = false;
			} else {
				$this->logger->debug('No cell given or cellValue is empty while loading columns for importing');
				if ($cell->getDataType() === 'null') {
					// LibreOffice generated XLSX doc may have more empty columns in the first row.
					// Continue without increasing error count, but leave a marker to detect gaps in titles.
					$lastCellWasEmpty = true;
					continue;
				}
				$this->countErrors++;
			}
			$secondRowCellIterator->next();
			$index++;
		}

		if ($hasGapInTitles) {
			$this->logger->info('Imported table is having a gap in column titles');
			$this->countErrors++;
		}

		$this->rawColumnTitles = $titles;
		$this->rawColumnDataTypes = $dataTypes;

		try {
			$this->columns = $this->columnService->findOrCreateColumnsByTitleForTableAsArray($this->tableId, $this->viewId, $titles, $dataTypes, $this->userId, $this->createUnknownColumns, $this->countCreatedColumns, $this->countMatchingColumns);
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
}
