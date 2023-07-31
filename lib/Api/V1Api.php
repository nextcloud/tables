<?php

namespace OCA\Tables\Api;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RowService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class V1Api {
	private RowService $rowService;

	private ColumnService $columnService;

	public function __construct(ColumnService $columnService, RowService $rowService) {
		$this->columnService = $columnService;
		$this->rowService = $rowService;
	}

	/**
	 * @param int $viewId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @param string $userId
	 * @return array
	 * @throws InternalError
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getData(int $viewId, ?int $limit, ?int $offset, string $userId):
	array {
		$columns = $this->columnService->findAllByView($viewId);

		$rows = $this->rowService->findAllByView($viewId, $userId, $limit, $offset);

		$data = [];

		// first line contains the titles
		$header = [];
		foreach ($columns as $column) {
			$header[] = $column->getTitle();
		}
		$data[] = $header;

		// now add the rows
		foreach ($rows as $row) {
			$rowData = $row->getDataArray();
			$line = [];
			foreach ($columns as $column) {
				$value = '';
				foreach ($rowData as $datum) {
					if ($datum['columnId'] === $column->getId()) {
						$value = $datum['value'];
					}
				}
				$line[] = $value;
			}
			$data[] = $line;
		}

		return $data;
	}
}
