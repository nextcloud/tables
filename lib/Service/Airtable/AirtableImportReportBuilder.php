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
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\TableService;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Creates an "Airtable import report" table at the end of an import job.
 *
 * The report table is an ordinary Nextcloud Tables table that lists every
 * field or value that could not be imported losslessly.  It is only created
 * when there is at least one entry to report — a clean import produces no
 * report table.
 *
 * Report row shape (as produced by AirtableColumnTypeInterface converters):
 *   - `object_name`   — column / field name from Airtable, or Airtable record ID for row-level errors
 *   - `object_type`   — `'field'`, `'value'`, `'row'`, or `'table'`
 *   - `airtable_type` — Airtable field type string, e.g. `'formula'` (empty for row/table errors)
 *   - `reason`        — human-readable explanation of the skip or downgrade
 *
 * The four Tables columns created mirror this shape:
 *   | Column        | Type         |
 *   |---------------|--------------|
 *   | Object name   | text / line  |
 *   | Object type   | text / line  |
 *   | Airtable type | text / line  |
 *   | Reason        | text / long  |
 */
class AirtableImportReportBuilder {

	private const REPORT_TABLE_TITLE = 'Airtable import report';
	private const REPORT_TABLE_EMOJI = '📋';

	public function __construct(
		private readonly TableService   $tableService,
		private readonly ColumnService  $columnService,
		private readonly IDBConnection  $db,
		private readonly LoggerInterface $logger,
	) {
	}

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Build the import-report table from the entries accumulated during the import.
	 *
	 * Skips table creation entirely when `$reportRows` is empty so that a
	 * fully-lossless import produces no extra artefacts.
	 *
	 * @param list<array{object_name: string, object_type: string, airtable_type: string, reason: string}> $reportRows
	 * @param string $userId  UID of the user who initiated the import.
	 * @param int    $jobId   Import job ID — used only for log context.
	 *
	 * @throws \OCA\Tables\Errors\InternalError|\OCA\Tables\Errors\NotFoundError|\OCA\Tables\Errors\PermissionError
	 */
	public function build(array $reportRows, string $userId, int $jobId): void {
		if (empty($reportRows)) {
			$this->logger->info('AirtableImportReportBuilder: no issues to report — skipping report table', [
				'app'    => Application::APP_ID,
				'job_id' => $jobId,
			]);
			return;
		}

		$rowCount = count($reportRows);
		$this->logger->info('AirtableImportReportBuilder: creating report table', [
			'app'       => Application::APP_ID,
			'job_id'    => $jobId,
			'row_count' => $rowCount,
		]);

		// Create the report table.
		$table = $this->tableService->create(
			self::REPORT_TABLE_TITLE,
			'custom',
			self::REPORT_TABLE_EMOJI,
			null,
			$userId,
		);

		$tableId = $table->getId();

		// Create the four report columns and remember their IDs.
		$columns = $this->createReportColumns($tableId, $userId);

		// Insert one row per accumulated report entry.
		$now = (new DateTime())->format('Y-m-d H:i:s');
		foreach ($reportRows as $entry) {
			$this->insertReportRow($tableId, $columns, $entry, $userId, $now);
		}

		$this->logger->info('AirtableImportReportBuilder: report table created', [
			'app'      => Application::APP_ID,
			'job_id'   => $jobId,
			'table_id' => $tableId,
			'rows'     => $rowCount,
		]);
	}

	// =========================================================================
	// Private — column creation
	// =========================================================================

	/**
	 * Create the four fixed report columns and return a map of
	 * `['object_name' => Column, 'object_type' => Column, ...]`.
	 *
	 * @return array<string, Column>
	 *
	 * @throws \OCA\Tables\Errors\InternalError|\OCA\Tables\Errors\NotFoundError|\OCA\Tables\Errors\PermissionError
	 */
	private function createReportColumns(int $tableId, string $userId): array {
		$defs = [
			'object_name'   => new ColumnDto(title: 'Object name',   type: 'text', subtype: 'line'),
			'object_type'   => new ColumnDto(title: 'Object type',   type: 'text', subtype: 'line'),
			'airtable_type' => new ColumnDto(title: 'Airtable type', type: 'text', subtype: 'line'),
			'reason'        => new ColumnDto(title: 'Reason',        type: 'text', subtype: 'long'),
		];

		$columns = [];
		foreach ($defs as $key => $dto) {
			$columns[$key] = $this->columnService->create($userId, $tableId, null, $dto);
		}

		return $columns;
	}

	// =========================================================================
	// Private — row insertion
	// =========================================================================

	/**
	 * Insert a single report entry as a row sleeve + four text cells.
	 *
	 * Mirrors the approach used by AirtableDataImporter: write directly to
	 * `oc_tables_row_sleeves` and the typed cell tables to avoid the
	 * HTTP-session dependency of RowService::create().
	 *
	 * @param array<string, Column> $columns
	 * @param array{object_name: string, object_type: string, airtable_type: string, reason: string} $entry
	 *
	 * @throws Exception on DB error.
	 */
	private function insertReportRow(
		int    $tableId,
		array  $columns,
		array  $entry,
		string $userId,
		string $now,
	): void {
		$rowId = $this->insertRowSleeve($tableId, $userId, $now);

		$values = [
			'object_name'   => $entry['object_name']   ?? '',
			'object_type'   => $entry['object_type']   ?? '',
			'airtable_type' => $entry['airtable_type'] ?? '',
			'reason'        => $entry['reason']        ?? '',
		];

		foreach ($values as $key => $value) {
			if (!isset($columns[$key])) {
				continue;
			}
			$this->insertTextCell($rowId, $columns[$key]->getId(), $value, $userId, $now);
		}
	}

	/**
	 * Insert a row sleeve and return its generated ID.
	 *
	 * @throws Exception on DB error.
	 */
	private function insertRowSleeve(int $tableId, string $userId, string $now): int {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('tables_row_sleeves')
			->values([
				'table_id'     => $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT),
				'created_by'   => $qb->createNamedParameter($userId),
				'created_at'   => $qb->createNamedParameter($now),
				'last_edit_by' => $qb->createNamedParameter($userId),
				'last_edit_at' => $qb->createNamedParameter($now),
			]);
		$qb->executeStatement();

		return (int) $this->db->lastInsertId('*PREFIX*tables_row_sleeves');
	}

	/**
	 * Insert a single text cell using the RowCellTextMapper so that
	 * type-specific serialisation is handled consistently.
	 *
	 * @throws Exception on DB error.
	 */
	private function insertTextCell(int $rowId, int $columnId, string $value, string $userId, string $now): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('tables_row_cells_text')
			->values([
				'row_id'       => $qb->createNamedParameter($rowId, IQueryBuilder::PARAM_INT),
				'column_id'    => $qb->createNamedParameter($columnId, IQueryBuilder::PARAM_INT),
				'value'        => $qb->createNamedParameter($value),
				'last_edit_by' => $qb->createNamedParameter($userId),
				'last_edit_at' => $qb->createNamedParameter($now),
			]);
		$qb->executeStatement();
	}
}
