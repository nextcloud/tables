<?php

namespace OCA\Tables\Api;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RowService;

class V1Api {
	private RowService $rowService;

	private ColumnService $columnService;

	public function __construct(ColumnService $columnService, RowService $rowService) {
		$this->columnService = $columnService;
		$this->rowService = $rowService;
	}

	/**
	 * @param int $tableId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function getData(int $viewId, ?int $limit, ?int $offset): array {
		$columns = $this->columnService->findAllByView($viewId);

		$rows = $this->rowService->findAllByView($viewId, $limit, $offset);

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
