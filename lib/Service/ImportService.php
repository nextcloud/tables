<?php


namespace OCA\Tables\Service;

use OC\User\NoUserException;
use OCA\Tables\Db\Column;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnTypes\IColumnTypeBusiness;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IUserManager;
use OCP\Server;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

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
	private int $countMatchingColumns = 0;
	private int $countCreatedColumns = 0;
	private int $countInsertedRows = 0;
	private int $countErrors = 0;
	private int $countParsingErrors = 0;

	private array $rawColumnTitles = [];
	private array $rawColumnDataTypes = [];
	private array $columnsConfig = [];

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, ?string $userId,
		IRootFolder $rootFolder, ColumnService $columnService, RowService $rowService, TableService $tableService, ViewService $viewService, IUserManager $userManager) {
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
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		$this->createUnknownColumns = false;
		$previewData = [];

		try {
			$userFolder = $this->rootFolder->getUserFolder($this->userId);
			$error = false;
			if ($userFolder->nodeExists($path)) {
				$file = $userFolder->get($path);
				$tmpFileName = $file->getStorage()->getLocalFile($file->getInternalPath());
				if($tmpFileName) {
					$spreadsheet = IOFactory::load($tmpFileName);
					$previewData = $this->getPreviewData($spreadsheet->getActiveSheet());
				} else {
					$error = true;
				}
			} elseif (\file_exists($path)) {
				$spreadsheet = IOFactory::load($path);
				$previewData = $this->getPreviewData($spreadsheet->getActiveSheet());
			} else {
				$error = true;
			}

			if($error) {
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
				$columns[] = [
					'title' => $title,
					'type' => $this->rawColumnDataTypes[$colIndex]['type'],
					'subtype' => $this->rawColumnDataTypes[$colIndex]['subtype'],
					'numberDecimals' => $this->rawColumnDataTypes[$colIndex]['number_decimals'] ?? 0,
					'numberPrefix' => $this->rawColumnDataTypes[$colIndex]['number_prefix'] ?? '',
					'numberSuffix' => $this->rawColumnDataTypes[$colIndex]['number_suffix'] ?? '',
				];
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

			foreach ($cellIterator as $cellIndex => $cell) {
				$value = $cell->getValue();
				$colIndex = (int) $cellIndex;
				$column = $this->columns[$colIndex];

				if (($column && $column->getType() === 'datetime') || (is_array($columns[$colIndex]) && $columns[$colIndex]['type'] === 'datetime')) {
					$value = Date::excelToDateTimeObject($value)->format('Y-m-d H:i');
				} elseif (($column && $column->getType() === 'number' && $column->getNumberSuffix() === '%')
					|| (is_array($columns[$colIndex]) && $columns[$colIndex]['type'] === 'number' && $columns[$colIndex]['numberSuffix'] === '%')) {
					$value = $value * 100;
				} elseif (($column && $column->getType() === 'selection' && $column->getSubtype() === 'check')
					|| (is_array($columns[$colIndex]) && $columns[$colIndex]['type'] === 'selection' && $columns[$colIndex]['subtype'] === 'check')) {
					$value = $cell->getFormattedValue() === 'TRUE' ? 'true' : 'false';
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
	 * @param int|null $tableId
	 * @param int|null $viewId
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
				throw new PermissionError('create row at the view id = '.$viewId.' is not allowed.');
			}
			if ($createMissingColumns && !$this->permissionsService->canManageTableById($view->getTableId())) {
				throw new PermissionError('create columns at the view id = '.$viewId.' is not allowed.');
			}
			$this->viewId = $viewId;
		} elseif ($tableId) {
			$table = $this->tableService->find($tableId);
			if (!$this->permissionsService->canCreateRows($table, 'table')) {
				throw new PermissionError('create row at the view id = '. (string) $viewId .' is not allowed.');
			}
			if ($createMissingColumns && !$this->permissionsService->canManageTable($table)) {
				throw new PermissionError('create columns at the view id = '. (string) $viewId .' is not allowed.');
			}
			$this->tableId = $tableId;
		} else {
			$e = new \Exception('Neither tableId nor viewId is given.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
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
				if($tmpFileName) {
					$spreadsheet = IOFactory::load($tmpFileName);
					$this->loop($spreadsheet->getActiveSheet());
				} else {
					$error = true;
				}
			} elseif (\file_exists($path)) {
				$spreadsheet = IOFactory::load($path);
				$this->loop($spreadsheet->getActiveSheet());
			} else {
				$error = true;
			}
			if($error) {
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
		$firstRow = $worksheet->getRowIterator()->current();
		$secondRow = $worksheet->getRowIterator()->seek(2)->current();
		$this->getColumns($firstRow, $secondRow);

		if (empty(array_filter($this->columns))) {
			return;
		}

		foreach ($worksheet->getRowIterator(2) as $row) {
			// parse row data
			$this->createRow($row);
		}
	}

	/*
	 * @return stringify value
	 */
	private function parseValueByColumnType(string $value, Column $column): string {
		try {
			$businessClassName = 'OCA\Tables\Service\ColumnTypes\\';
			$businessClassName .= ucfirst($column->getType()).ucfirst($column->getSubtype()).'Business';
			/** @var IColumnTypeBusiness $columnBusiness */
			$columnBusiness = Server::get($businessClassName);
			if(!$columnBusiness->canBeParsed($value, $column)) {
				$this->logger->warning('Value '.$value.' could not be parsed for column '.$column->getTitle());
				$this->countParsingErrors++;
				return '';
			}
			return $columnBusiness->parseValue($value, $column);
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
	private function createRow(Row $row): void {
		$cellIterator = $row->getCellIterator();
		$cellIterator->setIterateOnlyExistingCells(false);

		try {
			$i = -1;
			$data = [];
			$hasData = false;
			foreach ($cellIterator as $cell) {
				$i++;

				// only add the dataset if column is known
				if(!isset($this->columns[$i]) || $this->columns[$i] === '') {
					$this->logger->debug('Column unknown while fetching rows data for importing.');
					continue;
				}

				/** @var Column $column */
				$column = $this->columns[$i];

				// if cell is empty
				if(!$cell || $cell->getValue() === null) {
					$this->logger->info('Cell is empty while fetching rows data for importing.');
					if($column->getMandatory()) {
						$this->logger->warning('Mandatory column was not set');
						$this->countErrors++;
						return;
					}
					continue;
				}

				$value = $cell->getValue();
				$hasData = $hasData || !empty($value);
				if ($column->getType() === 'datetime') {
					$value = Date::excelToDateTimeObject($value)->format('Y-m-d H:i');
				} elseif ($column->getType() === 'number' && $column->getNumberSuffix() === '%') {
					$value = $value * 100;
				} elseif ($column->getType() === 'selection' && $column->getSubtype() === 'check') {
					$value = $cell->getFormattedValue() === 'TRUE' ? 'true' : 'false';
				}

				$data[] = [
					'columnId' => $column->getId(),
					'value' => json_decode($this->parseValueByColumnType($value, $column)),
				];
			}

			if ($hasData) {
				$this->rowService->create($this->tableId, $this->viewId, $data);
				$this->countInsertedRows++;
			} else {
				$this->logger->debug('Skipped empty row ' . $row->getRowIndex() . ' during import');
			}
		} catch (PermissionError $e) {
			$this->logger->error('Could not create row while importing, no permission.', ['exception' => $e]);
			$this->countErrors++;
		} catch (InternalError $e) {
			$this->logger->error('Error while creating  new row for import.', ['exception' => $e]);
			$this->countErrors++;
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (\Throwable $e) {
			$this->countErrors++;
			$this->logger->error('Error while creating new row for import.', ['exception' => $e]);
		}

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
		foreach ($cellIterator as $cell) {
			if ($cell && $cell->getValue() !== null && $cell->getValue() !== '') {
				$title = $cell->getValue();

				if (isset($this->columnsConfig[$index]) && $this->columnsConfig[$index]['action'] === 'exist' && $this->columnsConfig[$index]['existColumn']) {
					$title = $this->columnsConfig[$index]['existColumn']['label'];
					$countMatchingColumnsFromConfig++;
				}
				if (isset($this->columnsConfig[$index]) && $this->columnsConfig[$index]['action'] === 'new' && $this->createUnknownColumns) {
					$column = $this->columnService->create(
						$this->userId,
						$this->tableId,
						$this->viewId,
						$this->columnsConfig[$index]['type'],
						$this->columnsConfig[$index]['subtype'],
						$this->columnsConfig[$index]['title'],
						$this->columnsConfig[$index]['mandatory'] ?? false,
						$this->columnsConfig[$index]['description'] ?? '',
						$this->columnsConfig[$index]['textDefault'] ?? '',
						$this->columnsConfig[$index]['textAllowedPattern'] ?? '',
						$this->columnsConfig[$index]['textMaxLength'] ?? null,
						$this->columnsConfig[$index]['numberPrefix'] ?? '',
						$this->columnsConfig[$index]['numberSuffix'] ?? '',
						$this->columnsConfig[$index]['numberDefault'] ?? null,
						$this->columnsConfig[$index]['numberMin'] ?? null,
						$this->columnsConfig[$index]['numberMax'] ?? null,
						$this->columnsConfig[$index]['numberDecimals'] ?? 0,
						$this->columnsConfig[$index]['selectionOptions'] ?? '',
						$this->columnsConfig[$index]['selectionDefault'] ?? '',
						$this->columnsConfig[$index]['datetimeDefault'] ?? '',
						$this->columnsConfig[$index]['selectedViewIds'] ?? []
					);
					$title = $column->getTitle();
					$countCreatedColumnsFromConfig++;
				}
				$titles[] = $title;

				// Convert data type to our data type
				$dataTypes[] = $this->parseColumnDataType($secondRowCellIterator->current());
			} else {
				$this->logger->debug('No cell given or cellValue is empty while loading columns for importing');
				$this->countErrors++;
			}
			$secondRowCellIterator->next();
			$index++;
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
			'type' => 'text',
			'subtype' => 'line',
		];

		if (Date::isDateTime($cell) || $originDataType === DataType::TYPE_ISO_DATE) {
			$dataType = [
				'type' => 'datetime',
			];
		} elseif ($originDataType === DataType::TYPE_NUMERIC) {
			if (str_contains($formattedValue, '%')) {
				$dataType = [
					'type' => 'number',
					'number_decimals' => 2,
					'number_suffix' => '%',
				];
			} elseif (str_contains($formattedValue, '€')) {
				$dataType = [
					'type' => 'number',
					'number_decimals' => 2,
					'number_suffix' => '€',
				];
			} elseif (str_contains($formattedValue, 'EUR')) {
				$dataType = [
					'type' => 'number',
					'number_decimals' => 2,
					'number_suffix' => 'EUR',
				];
			} elseif (str_contains($formattedValue, '$')) {
				$dataType = [
					'type' => 'number',
					'number_decimals' => 2,
					'number_prefix' => '$',
				];
			} elseif (str_contains($formattedValue, 'USD')) {
				$dataType = [
					'type' => 'number',
					'number_decimals' => 2,
					'number_suffix' => 'USD',
				];
			} elseif (is_float($value)) {
				$decimals = strlen(substr(strrchr((string)$value, "."), 1));
				$dataType = [
					'type' => 'number',
					'number_decimals' => $decimals,
				];
			} else {
				$dataType = [
					'type' => 'number',
				];
			}
		} elseif ($originDataType === DataType::TYPE_BOOL) {
			$dataType = [
				'type' => 'selection',
				'subtype' => 'check',
				'selection_default' => 'false',
			];
		}

		return $dataType;
	}
}
