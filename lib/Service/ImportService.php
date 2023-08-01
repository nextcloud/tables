<?php


namespace OCA\Tables\Service;

use OC\User\NoUserException;
use OCA\Tables\Db\Column;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnTypes\TextLineBusiness;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IUserManager;
use OCP\Server;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class ImportService extends SuperService {

	private IRootFolder $rootFolder;
	private ColumnService $columnService;
	private RowService $rowService;
	private ViewService $viewService;
	private IUserManager $userManager;

	private int $viewId = -1;
	private array $columns = [];
	private bool $createUnknownColumns = true;
	private int $countMatchingColumns = 0;
	private int $countCreatedColumns = 0;
	private int $countInsertedRows = 0;
	private int $countErrors = 0;
	private int $countParsingErrors = 0;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, ?string $userId,
		IRootFolder $rootFolder, ColumnService $columnService, RowService $rowService, ViewService $viewService, IUserManager $userManager) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->rootFolder = $rootFolder;
		$this->columnService = $columnService;
		$this->rowService = $rowService;
		$this->viewService = $viewService;
		$this->userManager = $userManager;
	}

	/**
	 * @param int $viewId
	 * @param string $path
	 * @param bool $createMissingColumns
	 * @return array
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function import(int $viewId, string $path, bool $createMissingColumns = true): array {
		$view = $this->viewService->find($viewId);
		if (!$this->permissionsService->canCreateRows($view)) {
			throw new PermissionError('create row at the view id = '.$viewId.' is not allowed.');
		}
		if ($createMissingColumns && (!$view->getIsBaseView() || !$this->permissionsService->canManageTableById($view->getTableId()))) {
			throw new PermissionError('create columns at the view id = '.$viewId.' is not allowed.');
		}
		if ($this->userManager->get($this->userId) === null) {
			$error = 'No user in context, can not import data. Cancel.';
			$this->logger->debug($error);
			throw new InternalError($error);
		}

		$this->viewId = $viewId;
		$this->createUnknownColumns = $createMissingColumns;

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
		$firstRow = true;
		foreach ($worksheet->getRowIterator() as $row) {
			if ($firstRow) {
				$this->getColumns($row);
				if (empty(array_filter($this->columns))) {
					return;
				}
				$firstRow = false;
			} else {
				// parse row data
				$this->createRow($row);
			}
		}
	}

	/*
	 * @return stringify value
	 */
	private function parseValueByColumnType(string $value, Column $column): string {
		try {
			$businessClassName = 'OCA\Tables\Service\ColumnTypes\\';
			$businessClassName .= ucfirst($column->getType()).ucfirst($column->getSubtype()).'Business';
			/** @var TextLineBusiness $columnBusiness */
			$columnBusiness = Server::get($businessClassName);
			if(!$columnBusiness->canBeParsed($value, $column)) {
				$this->logger->warning('Value '.$value.' could not be parsed for column '.$column->getTitle());
				$this->countParsingErrors++;
				return '';
			}
			return $columnBusiness->parseValue($value, $column);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->debug('Column type business class not found');
		}
		return '';
	}

	/**
	 * @param Row $row
	 * @return void
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 */
	private function createRow(Row $row): void {
		$cellIterator = $row->getCellIterator();
		$cellIterator->setIterateOnlyExistingCells(false);

		$i = -1;
		$data = [];
		foreach ($cellIterator as $cell) {
			$i++;

			// only add the dataset if column is known
			if($this->columns[$i] === '' || !isset($this->columns[$i])) {
				$this->logger->debug('Column unknown while fetching rows data for importing.');
				continue;
			}

			// if cell is empty
			if(!$cell || $cell->getValue() === null) {
				$this->logger->info('Cell is empty while fetching rows data for importing.');
				if($this->columns[$i]->getMandatory()){
					$this->logger->warning('Mandatory column was not set');
					$this->countErrors++;
					return;
				}
				continue;
			}

			$data[] = [
				'columnId' => $this->columns[$i]->getId(),
				'value' => json_decode($this->parseValueByColumnType($cell->getValue(), $this->columns[$i])),
			];
		}
		try {
			$this->rowService->create($this->viewId, $data);
			$this->countInsertedRows++;
		} catch (PermissionError $e) {
			$this->logger->error('Could not create row while importing, no permission.', ['exception' => $e]);
			$this->countErrors++;
		} catch (Exception $e) {
			$this->logger->error('Error while creating  new row for import.', ['exception' => $e]);
			$this->countErrors++;
		}

	}

	/**
	 * @param Row $row
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function getColumns(Row $row): void {
		$cellIterator = $row->getCellIterator();
		$titles = [];
		foreach ($cellIterator as $cell) {
			if ($cell && $cell->getValue() !== null && $cell->getValue() !== '') {
				$titles[] = $cell->getValue();
			} else {
				$this->logger->debug('No cell given or cellValue is empty while loading columns for importing');
				$this->countErrors++;
			}
		}
		try {
			$this->columns = $this->columnService->findOrCreateColumnsByTitleForTableAsArray($this->viewId, $titles, $this->userId, $this->createUnknownColumns, $this->countCreatedColumns, $this->countMatchingColumns);
		} catch (Exception $e) {
			throw new InternalError($e->getMessage());
		}
	}

}
