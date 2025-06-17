<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Analytics;

use OCA\Analytics\Datasource\IDatasource;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class AnalyticsDatasource implements IDatasource {
	private LoggerInterface $logger;
	private IL10N $l10n;
	private TableService $tableService;
	private ViewService $viewService;
	private RowService $rowService;
	private ColumnService $columnService;

	protected ?string $userId;

	public function __construct(
		IL10N $l10n,
		LoggerInterface $logger,
		TableService $tableService,
		ViewService $viewService,
		ColumnService $columnService,
		RowService $rowService,
		?string $userId,
	) {
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->tableService = $tableService;
		$this->viewService = $viewService;
		$this->columnService = $columnService;
		$this->rowService = $rowService;
		$this->userId = $userId;
	}

	/**
	 * @return string Display Name of the datasource
	 */
	public function getName(): string {
		return $this->l10n->t('Nextcloud Tables');
	}

	/**
	 * @return int 2 digit unique datasource id
	 */
	public function getId(): int {
		return 55;
	}

	/**
	 * available options of the data source
	 *
	 * return needs to be an array and can consist of many fields.
	 * every field needs to provide the following format
	 *  id          *mandatory*     = name of the option for the readData
	 *  name        *mandatory*     = displayed name of the input field in the UI
	 *  type        *optional*      = 'tf' to create a dropdown. Values need to be provided in the placeholder separated with "/".
	 *  placeholder *mandatory*     = help text for the input field in the UI
	 *                                for type=tf:
	 *                                  e.g. "true/false"
	 *                                  if value/text pairs are required for the dropdown/option, the values need to be separated with "-" in addition.
	 *                                  e.g. "eq-equal/gt-greater"
	 *                                  to avoid translation of the technical strings, separate them
	 *                                  'true-' - $this->l10n->t('Yes').'/false-'.$this->l10n->t('No')
	 *
	 *  example:
	 *  {['id' => 'datatype', 'name' => 'Type of Data', 'type' => 'tf', 'placeholder' => 'adaptation/absolute']}
	 *
	 * @return array
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function getTemplate(): array {
		$tableString = '';
		$template = [];
		$tables = [];

		// get all tables for the user
		if ($this->userId) {
			$tables = $this->tableService->findAll($this->userId);
		}

		// get all tables and subsequent views
		foreach ($tables as $table) {
			$tableString = $tableString . $table->getId() . '-' . $table->getTitle() . '/';
			// get all views per table
			try {
				$views = $this->viewService->findAll($this->tableService->find($table->getId()), $this->userId);
				foreach ($views as $view) {
					// concatenate the option-string. The format is tableId:viewId-title
					$tableString = $tableString . $table->getId() . ':' . $view->getId() . '-' . $view->getTitle() . '/';
				}
			} catch (PermissionError $e) {
				// this is a shared table without shared views;
				continue;
			}
		}

		// get shared views without table authorization
		$views = $this->viewService->findSharedViewsWithMe($this->userId);
		foreach ($views as $view) {
			$tableString = $tableString . $view->getTableId() . ':' . $view->getId() . '-' . $view->getTitle() . '/';
		}
		// add the tables to a dropdown in the data source settings
		$template[] = ['id' => 'tableId', 'name' => $this->l10n->t('Select table'), 'type' => 'tf', 'placeholder' => $tableString];
		$template[] = ['id' => 'columns', 'name' => $this->l10n->t('Select columns'), 'placeholder' => $this->l10n->t('e.g. 1,2,4 or leave empty'), 'type' => 'columnPicker'];
		$template[] = ['id' => 'timestamp', 'name' => $this->l10n->t('Timestamp of data load'), 'placeholder' => 'false-' . $this->l10n->t('No') . '/true-' . $this->l10n->t('Yes'), 'type' => 'tf'];
		return $template;
	}

	/**
	 * Read the Data
	 *
	 * return needs to be an array
	 *  [
	 *      'header' => $header,  //array('column header 1', 'column header 2','column header 3')
	 *      'dimensions' => array_slice($header, 0, count($header) - 1),
	 *      'data' => $data,
	 *      'error' => 0,         // INT 0 = no error
	 *  ]
	 *
	 * @param array $option
	 * @return array available options of the data source
	 * @throws InternalError
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function readData($option): array {
		// get the ids which come in the format tableId:viewId
		$ids = explode(':', $option['tableId']);
		$this->userId = $option['user_id'];

		if (count($ids) === 1) {
			// it's a table
			$data = $this->getData((int)$ids[0], null, null);
		} elseif (count($ids) === 2) {
			// it's a view
			$data = $this->getData((int)$ids[1], null, null, 'view');
		}

		// extract the header from the first row
		$header = $data[0];
		// continue with the data without header
		$rows = array_slice($data, 1);

		// get the selected columns from the data source options
		$selectedColumns = [];
		if (isset($option['columns']) && strlen($option['columns']) > 0) {
			$selectedColumns = str_getcsv($option['columns']);
		}

		$data = [];
		if (count($selectedColumns) !== 0) {
			// if dedicated columns are selected, the others are removed
			$header = $this->minimizeRow($selectedColumns, $header);
			foreach ($rows as $row) {
				$data[] = $this->minimizeRow($selectedColumns, $row);
			}
		} else {
			foreach ($rows as $row) {
				$data[] = $row;
			}
		}
		unset($rows);

		return ['header' => $header, 'dimensions' => array_slice($header, 0, count($header) - 1), 'data' => $data, //'rawdata' => $data,
			'error' => 0,];
	}

	/**
	 * Get the data from the table backend
	 * this was taken over from the former API
	 *
	 * @param int $nodeId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @param string|null $nodeType
	 * @return array
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	private function getData(int $nodeId, ?int $limit, ?int $offset, ?string $nodeType = null): array {
		if ($nodeType === 'view') {
			$columns = $this->columnService->findAllByView($nodeId, $this->userId);
			$rows = $this->rowService->findAllByView($nodeId, $this->userId, $limit, $offset);
		} else {
			// if no nodeType is provided, the old table selection is used to not break anything
			$columns = $this->columnService->findAllByTable($nodeId, $this->userId);
			$rows = $this->rowService->findAllByTable($nodeId, $this->userId, $limit, $offset);
		}

		$data = [];

		// first line contains the titles
		$header = [];
		foreach ($columns as $column) {
			$header[] = $column->getTitle();
		}
		$data[] = $header;

		// now add the rows
		foreach ($rows as $row) {
			$rowData = $row->getData();
			$line = [];
			foreach ($columns as $column) {
				$value = '';
				foreach ($rowData as $datum) {
					if ($datum['columnId'] === $column->getId()) {
						// if column type selection, the corresponding labels need to be fetched
						if ($column->getType() === 'selection') {
							foreach ($column->getSelectionOptionsArray() as $option) {
								if ($option['id'] === $datum['value']) {
									$value = $option['label'];
								}
							}
						} else {
							$value = $datum['value'];
						}
					}
				}
				// Tables does not deliver any values for "blank" default columns
				if ($value === '' && $column->getType() === 'number') {
					$value = $column->getNumberDefault();
				}
				$line[] = $value;
			}
			$data[] = $line;
		}
		return $data;
	}

	/**
	 * filter only the selected columns in the given sequence
	 *
	 * @param array $selectedColumns
	 * @param array $row
	 * @return array
	 */
	private function minimizeRow(array $selectedColumns, array $row): array {
		$rowMinimized = [];
		foreach ($selectedColumns as $selectedColumn) {
			if ((int)$selectedColumn) {
				$rowMinimized[] = $row[$selectedColumn - 1];
			} else {
				$rowMinimized[] = $selectedColumn;
			}
		}
		return $rowMinimized;
	}
}
