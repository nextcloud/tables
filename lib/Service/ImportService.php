<?php


namespace OCA\Tables\Service;

use OC\User\NoUserException;
use OCA\Tables\Db\Column;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnTypes\TextLineBusiness;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
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

	private int $tableId = -1;
	private array $columns = [];
	private bool $createUnknownColumns = true;
	private int $countCreatedColumns = 0;
	private int $countInsertedRows = 0;
	private int $countErrors = 0;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, ?string $userId,
		IRootFolder $rootFolder, ColumnService $columnService, RowService $rowService) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->rootFolder = $rootFolder;
		$this->columnService = $columnService;
		$this->rowService = $rowService;
	}

	/**
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws InternalError
	 * @throws NotFoundError
	 */
	public function import(int $tableId, string $path, bool $createMissingColumns = true): array {
		if (!$this->userId) {
			$error = 'No user in context, can not import data. Cancel.';
			$this->logger->debug($error);
			throw new InternalError($error);
		}

		$this->tableId = $tableId;
		$this->createUnknownColumns = $createMissingColumns;

		/** @var IConfig $config */
		$config = Server::get(IConfig::class);

		try {
			$storageHome = $this->rootFolder->getUserFolder($this->userId);
			if ($storageHome->nodeExists($path)) {
				// TODO is this working if a object storage is used?
				$completePath = $storageHome->get($path)->getPath();
				$basePath = $config->getSystemValue('datadirectory');
				$spreadsheet = IOFactory::load($basePath.$completePath);
				$this->loop($spreadsheet->getActiveSheet());
			}

		} catch (NotFoundException|NotPermittedException|NoUserException|InternalError|PermissionError $e) {
			throw new NotFoundError('path could not be found');
		}

		return [
			'found columns' => count($this->columns),
			'created columns' => $this->countCreatedColumns,
			'inserted rows' => $this->countInsertedRows,
			'errors (see logs)' => $this->countErrors,
		];
	}

	/**
	 * @throws PermissionError
	 * @throws InternalError
	 */
	private function loop(Worksheet $worksheet): void {
		$firstRow = true;
		foreach ($worksheet->getRowIterator() as $row) {
			if ($firstRow) {
				$this->getColumns($row);
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
			/** @noinspection PhpUndefinedMethodInspection */
			$businessClassName .= ucfirst($column->getType()).ucfirst($column->getSubtype()).'Business';
			/** @var TextLineBusiness $columnBusiness */
			$columnBusiness = Server::get($businessClassName);
			return $columnBusiness->parseValue($value, $column);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->debug('Column type business class not found');
		}
		return '';
	}

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
				$this->countErrors++;
				continue;
			}

			// if cell is empty
			if(!$cell || $cell->getValue() === null) {
				$this->logger->info('Cell is empty while fetching rows data for importing.');
				continue;
			}

			$data[] = [
				'columnId' => $this->columns[$i]->getId(),
				'value' => json_decode($this->parseValueByColumnType($cell->getValue(), $this->columns[$i])),
			];
		}
		try {
			$this->rowService->createComplete($this->tableId, $data);
			$this->countInsertedRows++;
		} catch (PermissionError|Exception $e) {
			$this->logger->error('Could not create row while importing.');
			$this->countErrors++;
		}

	}

	/**
	 * @throws PermissionError
	 * @throws InternalError
	 */
	private function getColumns(Row $row): void {
		$cellIterator = $row->getCellIterator();
		$titles = [];
		foreach ($cellIterator as $cell) {
			if ($cell) {
				$titles[] = $cell->getValue();
			} else {
				$this->logger->debug('no cell given while loading columns for importing');
				$this->countErrors++;
			}
		}
		$this->columns = $this->columnService->findColumnsByTitleForTableAsArray($this->tableId, $titles, $this->userId, $this->createUnknownColumns, $this->countCreatedColumns);
	}

}
