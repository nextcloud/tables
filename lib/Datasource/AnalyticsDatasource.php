<?php
/**
 * Analytics data source
 * Report Table App data with the Analytics App
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 */

namespace OCA\Tables\Datasource;

use OCA\Analytics\Datasource\IDatasource;
use OCA\Tables\Api\V1Api;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\TableService;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class AnalyticsDatasource implements IDatasource {
	private LoggerInterface $logger;
	private IL10N $l10n;
	private TableService $tableService;
	private V1Api $api;

	protected ?string $userId;

	public function __construct(
		IL10N           $l10n,
		LoggerInterface $logger,
		TableService    $tableService,
		V1Api           $api,
		string          $userId
	) {
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->tableService = $tableService;
		$this->api = $api;
		$this->userId = $userId;
	}

	/**
	 * @return string Display Name of the datasource
	 */
	public function getName(): string {
		return $this->l10n->t('Nextcloud tables');
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
	 * @throws InternalError
	 */
	public function getTemplate(): array {
		$tableString = '';
		$template = [];

		// get all tables for the current user and concatenate the placeholder string
		if ($this->userId) {
			$tables = $this->tableService->findAll($this->userId);
		} else {
			$tables = [];
		}
		foreach ($tables as $table) {
			$tableString = $tableString . $table->jsonSerialize()['id'] . '-' . $table->jsonSerialize()['title'] . '/';
		}

		// add the tables to a dropdown in the data source settings
		$template[] = ['id' => 'tableId', 'name' => $this->l10n->t('Select table'), 'type' => 'tf', 'placeholder' => $tableString];
		$template[] = ['id' => 'columns', 'name' => $this->l10n->t('Select columns'), 'placeholder' => $this->l10n->t('e.g. 1,2,4 or leave empty'), 'type' => 'columnPicker'];
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
	 */
	public function readData($option): array {
		// get the data for the selected table
		$tableId = $option['tableId'];
		$data = $this->api->getData($tableId, null, null);

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

		return [
			'header' => $header,
			'dimensions' => array_slice($header, 0, count($header) - 1),
			'data' => $data,
			'rawdata' => $data,
			'error' => 0,
		];
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
			if (intval($selectedColumn)) {
				$rowMinimized[] = $row[$selectedColumn - 1];
			} else {
				$rowMinimized[] = $selectedColumn;
			}
		}
		return $rowMinimized;
	}
}
