<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable;

use DateTime;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\RowCellMapperSuper;
use OCA\Tables\Db\RowCellSuper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Http\Client\IClientService;
use OCP\IDBConnection;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Fetches Airtable row data page-by-page and bulk-inserts each row into
 * Nextcloud Tables using the mapped column IDs created by AirtableSchemaConverter.
 *
 * Row data is read from the same Airtable internal API surface that the share
 * page exposes (no PAT required).  The endpoint is cursor-paginated with a
 * page size of 100 records — the maximum Airtable allows.
 *
 * Cell values are converted through the registered AirtableColumnTypeInterface
 * converters, then persisted directly via the typed cell mapper classes
 * (RowCellTextMapper, RowCellNumberMapper, …) to bypass the HTTP-request
 * oriented RowService permission-check logic that requires an authenticated
 * user session.
 *
 * Progress is tracked by incrementing the `progress_done` counter on the
 * matching `oc_tables_airtable_imports` row after every successfully inserted
 * record.  Table-level failures are caught, logged, and appended to
 * `$reportRows`; the import continues with the next table rather than
 * aborting the entire job (Risk R9 mitigation).
 */
class AirtableDataImporter {

	/** Airtable internal endpoint for paginated table row data. ⚠ Undocumented — verify against live Airtable share pages when testing. */
	private const BASE_URL       = 'https://airtable.com';
	private const ROWS_PATH      = '/v0.3/application/%s/readTable/%s';
	private const PAGE_SIZE      = 100;
	private const REQUEST_TIMEOUT = 30;

	/** Maximum HTTP retries per page fetch before giving up on the table. */
	private const MAX_RETRIES = 3;

	public function __construct(
		private readonly IClientService $clientService,
		private readonly IDBConnection  $db,
		private readonly LoggerInterface $logger,
	) {
	}

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Iterate over every entry in `$tableMapping`, fetch rows from Airtable,
	 * convert cell values, and insert them into Nextcloud Tables.
	 *
	 * @param string $appId          Airtable application ID, e.g. `appXXXXXXXX`.
	 * @param array  $tableMapping   Map of Airtable table ID →
	 *                               `['tableId' => int, 'columnMapping' => [...]]`.
	 *                               Each `columnMapping` entry is a map of
	 *                               Airtable column ID →
	 *                               `['column' => Column, 'rawColumn' => [], 'converter' => AirtableColumnTypeInterface]`
	 *                               or `null` if the column was skipped.
	 * @param array  $requestHeaders HTTP headers extracted from the Airtable share page.
	 * @param array  $cookies        Optional session cookies (`__Host-airtable-session`).
	 * @param int    $jobId          Row ID in `oc_tables_airtable_imports` for progress tracking.
	 * @param string $userId         UID of the user who initiated the import.
	 * @param array  &$reportRows    Accumulated import-report entries; new entries for
	 *                               row-level or table-level failures are appended here.
	 */
	public function import(
		string $appId,
		array  $tableMapping,
		array  $requestHeaders,
		array  $cookies,
		int    $jobId,
		string $userId,
		array  &$reportRows,
	): void {
		$tableCount = count($tableMapping);
		$this->logger->info('AirtableDataImporter: starting import', [
			'app'         => Application::APP_ID,
			'app_id'      => $appId,
			'job_id'      => $jobId,
			'table_count' => $tableCount,
		]);

		$tableIndex = 0;
		foreach ($tableMapping as $airtableTableId => $tableInfo) {
			$tableIndex++;
			try {
				$this->importTable(
					$appId,
					(string) $airtableTableId,
					$tableInfo,
					$requestHeaders,
					$cookies,
					$jobId,
					$userId,
					$reportRows,
					$tableIndex,
					$tableCount,
				);
			} catch (\Throwable $e) {
				$tableName = $tableInfo['tableName'] ?? $airtableTableId;
				$this->logger->error('AirtableDataImporter: failed to import table', [
					'app'             => Application::APP_ID,
					'airtable_table'  => $airtableTableId,
					'tables_table_id' => $tableInfo['tableId'] ?? null,
					'exception'       => $e,
				]);
				$reportRows[] = [
					'object_name'   => (string) $tableName,
					'object_type'   => 'table',
					'airtable_type' => '',
					'reason'        => 'Table row import failed: ' . $e->getMessage(),
				];
			}
		}

		$this->logger->info('AirtableDataImporter: import finished', [
			'app'    => Application::APP_ID,
			'job_id' => $jobId,
		]);
	}

	// =========================================================================
	// Private — per-table orchestration
	// =========================================================================

	/**
	 * @param array  $tableInfo    `['tableId' => int, 'tableName' => string, 'columnMapping' => [...]]`
	 * @param array  &$reportRows  Accumulated report rows, passed by reference.
	 * @param int    $tableIndex   1-based position of this table in the overall import (for log context).
	 * @param int    $tableCount   Total number of tables being imported (for log context).
	 *
	 * @throws AirtableFetchException on unrecoverable HTTP / parse errors.
	 */
	private function importTable(
		string $appId,
		string $airtableTableId,
		array  $tableInfo,
		array  $requestHeaders,
		array  $cookies,
		int    $jobId,
		string $userId,
		array  &$reportRows,
		int    $tableIndex = 1,
		int    $tableCount = 1,
	): void {
		$tablesTableId = (int) $tableInfo['tableId'];
		$tableName     = $tableInfo['tableName'] ?? $airtableTableId;
		$columnMapping = $tableInfo['columnMapping'] ?? [];
		$cursor        = null;
		$pageNumber    = 0;
		$totalRows     = 0;

		$this->logger->info('AirtableDataImporter: importing table', [
			'app'             => Application::APP_ID,
			'table_progress'  => $tableIndex . '/' . $tableCount,
			'table_name'      => $tableName,
			'airtable_table'  => $airtableTableId,
			'tables_table_id' => $tablesTableId,
		]);

		do {
			$page       = $this->fetchPage($appId, $airtableTableId, $requestHeaders, $cookies, $cursor);
			$rows       = $page['rows'] ?? $page['records'] ?? [];
			$pageNumber++;
			$pageRows   = count($rows);

			foreach ($rows as $row) {
				try {
					$this->importRow($tablesTableId, $columnMapping, $row, $userId, $reportRows);
				} catch (\Throwable $e) {
					$rowId = $row['id'] ?? '?';
					$this->logger->warning('AirtableDataImporter: failed to import row', [
						'app'        => Application::APP_ID,
						'record_id'  => $rowId,
						'table_id'   => $tablesTableId,
						'exception'  => $e,
					]);
					$reportRows[] = [
						'object_name'   => (string) $rowId,
						'object_type'   => 'row',
						'airtable_type' => '',
						'reason'        => 'Row could not be imported: ' . $e->getMessage(),
					];
				}

				$totalRows++;
				$this->incrementProgress($jobId);
			}

			$cursor = $page['cursor'] ?? null;

			$this->logger->info('AirtableDataImporter: page imported', [
				'app'               => Application::APP_ID,
				'table_name'        => $tableName,
				'page'              => $pageNumber,
				'rows_on_page'      => $pageRows,
				'rows_imported'     => $totalRows,
				'more_pages'        => ($cursor !== null && $cursor !== ''),
			]);
		} while ($cursor !== null && $cursor !== '');

		$this->logger->info('AirtableDataImporter: table import complete', [
			'app'             => Application::APP_ID,
			'table_progress'  => $tableIndex . '/' . $tableCount,
			'table_name'      => $tableName,
			'total_rows'      => $totalRows,
			'pages_fetched'   => $pageNumber,
		]);
	}

	// =========================================================================
	// Private — single-record import
	// =========================================================================

	/**
	 * Insert one Airtable record as a Tables row sleeve + individual cell rows.
	 *
	 * @param int    $tablesTableId  Tables table ID.
	 * @param array  $columnMapping  Airtable column ID →
	 *                               `['column' => Column, 'rawColumn' => [], 'converter' => ...]`
	 *                               or `null`.
	 * @param array  $row            Raw Airtable record with `cellValuesByColumnId`.
	 * @param string $userId         The importing user.
	 * @param array  &$reportRows    Appended to on per-value conversion failures.
	 *
	 * @throws Exception on DB insertion failure.
	 */
	private function importRow(
		int    $tablesTableId,
		array  $columnMapping,
		array  $row,
		string $userId,
		array  &$reportRows,
	): void {
		$now          = (new DateTime())->format('Y-m-d H:i:s');
		$cellsByColId = $row['cellValuesByColumnId'] ?? $row['fields'] ?? [];

		// Insert the row sleeve first so that cells can reference its ID.
		$rowId = $this->insertRowSleeve($tablesTableId, $userId, $now);

		foreach ($columnMapping as $airtableColId => $colInfo) {
			if ($colInfo === null) {
				// Column was skipped during schema conversion.
				continue;
			}

			/** @var Column $column */
			$column    = $colInfo['column'];
			$rawColumn = $colInfo['rawColumn'];
			$converter = $colInfo['converter'];

			$rawValue       = $cellsByColId[$airtableColId] ?? null;
			$convertedValue = $converter->toTablesValue($rawValue, $rawColumn, $reportRows);

			if ($convertedValue === null) {
				continue;
			}

			$this->insertCell($rowId, $column, $convertedValue, $userId, $now);
		}
	}

	// =========================================================================
	// Private — HTTP
	// =========================================================================

	/**
	 * Fetch one page of Airtable row data with exponential back-off retries.
	 *
	 * The response is expected to contain:
	 *   - `rows` or `records` — array of record objects with `cellValuesByColumnId`.
	 *   - `cursor` — opaque string for the next page, absent / null on the last page.
	 *
	 * @param string      $appId          Airtable application ID.
	 * @param string      $tableId        Airtable table ID (tblXXXX).
	 * @param array       $requestHeaders Headers from the share page scrape.
	 * @param array       $cookies        Optional session cookies.
	 * @param string|null $cursor         Continuation cursor from a previous page response.
	 *
	 * @return array Decoded JSON response body.
	 *
	 * @throws AirtableFetchException on network errors or non-JSON responses.
	 */
	private function fetchPage(
		string  $appId,
		string  $tableId,
		array   $requestHeaders,
		array   $cookies,
		?string $cursor,
	): array {
		$url = self::BASE_URL . sprintf(self::ROWS_PATH, $appId, $tableId);

		$queryParams = ['pageSize' => self::PAGE_SIZE];
		if ($cursor !== null && $cursor !== '') {
			$queryParams['cursor'] = $cursor;
		}
		$url .= '?' . http_build_query($queryParams);

		$headers = array_merge($requestHeaders, [
			'Accept'     => 'application/json',
			'User-Agent' => 'Mozilla/5.0 (compatible; NextcloudTables)',
		]);

		if (!empty($cookies)) {
			$headers['Cookie'] = implode('; ', array_map(
				static fn(string $k, string $v): string => $k . '=' . $v,
				array_keys($cookies),
				array_values($cookies),
			));
		}

		$body          = null;
		$lastException = null;

		for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
			if ($attempt > 0) {
				// Exponential back-off: 1 s → 2 s → 4 s  (R8 mitigation).
				sleep(1 << ($attempt - 1));
			}

			try {
				$response = $this->clientService->newClient()->get($url, [
					'timeout' => self::REQUEST_TIMEOUT,
					'headers' => $headers,
				]);
				$body = (string) $response->getBody();
				break;
			} catch (\Exception $e) {
				$lastException = $e;
				$this->logger->warning(
					'AirtableDataImporter: HTTP error (attempt ' . ($attempt + 1) . '/' . self::MAX_RETRIES . ')',
					[
						'app'       => Application::APP_ID,
						'table_id'  => $tableId,
						'exception' => $e,
					]
				);
			}
		}

		if ($body === null) {
			throw new AirtableFetchException(
				'Network error while fetching rows for Airtable table "' . $tableId . '" after '
				. self::MAX_RETRIES . ' attempts: '
				. ($lastException?->getMessage() ?? 'unknown error'),
				0,
				$lastException,
			);
		}

		$decoded = json_decode($body, true);
		if (!is_array($decoded)) {
			throw new AirtableFetchException(
				'Airtable rows endpoint returned non-JSON for table "' . $tableId . '".'
			);
		}

		return $decoded;
	}

	// =========================================================================
	// Private — DB helpers
	// =========================================================================

	/**
	 * Insert a row sleeve with explicit user attribution and return the new row ID.
	 *
	 * This bypasses RowService (which requires an HTTP-request user session) and
	 * writes directly to `oc_tables_row_sleeves`, mirroring the approach used by
	 * `RowService::importRow()` in the user-migration code path.
	 *
	 * @throws Exception on DB error.
	 */
	private function insertRowSleeve(int $tableId, string $userId, string $now): int {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('tables_row_sleeves')
			->values([
				'table_id'    => $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT),
				'created_by'  => $qb->createNamedParameter($userId),
				'created_at'  => $qb->createNamedParameter($now),
				'last_edit_by' => $qb->createNamedParameter($userId),
				'last_edit_at' => $qb->createNamedParameter($now),
			]);
		$qb->executeStatement();

		return (int) $this->db->lastInsertId('*PREFIX*tables_row_sleeves');
	}

	/**
	 * Resolve the typed cell mapper and entity class for a given column, then
	 * persist the converted value.
	 *
	 * For cell types that support multiple values per column (currently only
	 * `usergroup`), the value is expected to be an array; one entity is written
	 * per element.  For all other types a single entity is written.
	 *
	 * The concrete mapper's `applyDataToEntity()` is called so that type-specific
	 * serialisation (e.g. JSON-encoding of selection / check values) is handled
	 * identically to the normal RowService write path.
	 */
	private function insertCell(
		int    $rowId,
		Column $column,
		mixed  $value,
		string $userId,
		string $now,
	): void {
		$type            = $column->getType();
		$cellMapperClass = 'OCA\\Tables\\Db\\RowCell' . ucfirst($type) . 'Mapper';
		$cellClass       = 'OCA\\Tables\\Db\\RowCell' . ucfirst($type);

		try {
			/** @var RowCellMapperSuper $mapper */
			$mapper = Server::get($cellMapperClass);
		} catch (\Throwable $e) {
			$this->logger->warning('AirtableDataImporter: no cell mapper for column type "' . $type . '", skipping cell', [
				'app'       => Application::APP_ID,
				'column_id' => $column->getId(),
				'type'      => $type,
			]);
			return;
		}

		$insertOne = function (mixed $singleValue) use ($rowId, $column, $mapper, $cellClass, $userId, $now): void {
			/** @var RowCellSuper $cell */
			$cell = new $cellClass();
			$cell->setRowIdWrapper($rowId);
			$cell->setColumnIdWrapper($column->getId());
			$cell->setLastEditBy($userId);
			$cell->setLastEditAt($now);
			$mapper->applyDataToEntity($column, $cell, $singleValue);
			$mapper->insert($cell);
		};

		if ($mapper->hasMultipleValues() && is_array($value)) {
			foreach ($value as $singleValue) {
				$insertOne($singleValue);
			}
		} else {
			$insertOne($value);
		}
	}

	/**
	 * Increment `progress_done` on the import job row by 1.
	 *
	 * Uses a raw SQL expression to avoid a read-modify-write cycle and to keep
	 * progress updates concurrency-safe.
	 */
	private function incrementProgress(int $jobId): void {
		$now = (new DateTime())->format('Y-m-d H:i:s');
		$this->db->executeStatement(
			'UPDATE `*PREFIX*tables_airtable_imports`'
			. ' SET `progress_done` = `progress_done` + 1, `updated_at` = ?'
			. ' WHERE `id` = ?',
			[$now, $jobId],
		);
	}
}
