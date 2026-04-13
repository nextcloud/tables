<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Column;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\TableService;
use Psr\Log\LoggerInterface;

/**
 * Converts an Airtable schema into Nextcloud Tables tables and columns.
 *
 * Takes the `data` payload returned by AirtableFetcher::fetchAll() and,
 * for each Airtable table schema, creates a matching Nextcloud Tables table
 * and a column for every supported Airtable field.  Unsupported or skipped
 * fields are logged to $reportRows for the import-report table.
 *
 * Returns a $tableMapping that AirtableDataImporter uses to write row data,
 * and a list of created table IDs that the notification/UI deep-link needs.
 */
class AirtableSchemaConverter {

	public function __construct(
		private readonly TableService $tableService,
		private readonly ColumnService $columnService,
		private readonly AirtableColumnTypeRegistry $registry,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Convert all tables and columns from the Airtable schema.
	 *
	 * @param array<string, mixed> $schema      The `data` payload from AirtableFetcher::fetchAll().
	 * @param string               $userId      UID of the importing user.
	 * @param array                &$reportRows Accumulator for import-report entries.
	 *
	 * @return array{
	 *   tableMapping: array<string, array{tableId: int, tableName: string, columnMapping: array<string, array{column: Column, rawColumn: array, converter: AirtableColumnTypeInterface}|null>}>,
	 *   importedTableIds: list<int>
	 * }
	 *
	 * @throws \OCA\Tables\Errors\InternalError|\OCA\Tables\Errors\NotFoundError|\OCA\Tables\Errors\PermissionError
	 */
	public function convert(array $schema, string $userId, array &$reportRows): array {
		$tableSchemas     = $schema['tableSchemas'] ?? [];
		$tableMapping     = [];
		$importedTableIds = [];

		$this->logger->info('AirtableSchemaConverter: converting schema', [
			'app'         => Application::APP_ID,
			'table_count' => count($tableSchemas),
		]);

		foreach ($tableSchemas as $rawTable) {
			$airtableTableId = (string) ($rawTable['id']   ?? '');
			$tableName       = (string) ($rawTable['name'] ?? 'Imported table');

			$this->logger->info('AirtableSchemaConverter: creating table', [
				'app'        => Application::APP_ID,
				'table_name' => $tableName,
			]);

			$table         = $this->tableService->create($tableName, 'custom', null, null, $userId);
			$tablesTableId = $table->getId();
			$importedTableIds[] = $tablesTableId;

			$columnMapping = $this->convertColumns($rawTable, $tablesTableId, $userId, $reportRows);

			$tableMapping[$airtableTableId] = [
				'tableId'       => $tablesTableId,
				'tableName'     => $tableName,
				'columnMapping' => $columnMapping,
			];
		}

		$this->logger->info('AirtableSchemaConverter: schema conversion done', [
			'app'               => Application::APP_ID,
			'tables_created'    => count($importedTableIds),
		]);

		return [
			'tableMapping'     => $tableMapping,
			'importedTableIds' => $importedTableIds,
		];
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert the columns of a single Airtable table schema.
	 *
	 * @param array<string, mixed> $rawTable
	 * @return array<string, array{column: Column, rawColumn: array, converter: AirtableColumnTypeInterface}|null>
	 */
	private function convertColumns(array $rawTable, int $tablesTableId, string $userId, array &$reportRows): array {
		// Airtable uses 'columns' in v0.3 schema; some exports use 'fields'.
		$rawColumns    = $rawTable['columns'] ?? $rawTable['fields'] ?? [];
		$columnMapping = [];

		foreach ($rawColumns as $rawColumn) {
			$airtableColId = (string) ($rawColumn['id']   ?? '');
			$airtableType  = (string) ($rawColumn['type'] ?? '');
			$columnName    = (string) ($rawColumn['name'] ?? $airtableColId);

			if ($airtableColId === '') {
				continue;
			}

			$converter = $this->registry->get($airtableType);

			if ($converter === null) {
				// Completely unknown type — skip and report.
				$this->logger->warning('AirtableSchemaConverter: unknown Airtable type, skipping column', [
					'app'           => Application::APP_ID,
					'column_name'   => $columnName,
					'airtable_type' => $airtableType,
				]);
				$reportRows[] = [
					'object_name'   => $columnName,
					'object_type'   => 'field',
					'airtable_type' => $airtableType,
					'reason'        => 'Field type "' . $airtableType . '" is not supported by this version of the importer.',
				];
				$columnMapping[$airtableColId] = null;
				continue;
			}

			$columnDto = $converter->toTablesColumn($rawColumn, $reportRows);

			if ($columnDto === null) {
				// Skip-and-report converter (formula, lookup, etc.) — no column to create.
				$columnMapping[$airtableColId] = null;
				continue;
			}

			$this->logger->debug('AirtableSchemaConverter: creating column', [
				'app'           => Application::APP_ID,
				'column_name'   => $columnName,
				'airtable_type' => $airtableType,
				'tables_type'   => $columnDto->getType(),
			]);

			try {
				$column = $this->columnService->create($userId, $tablesTableId, null, $columnDto);
				$columnMapping[$airtableColId] = [
					'column'    => $column,
					'rawColumn' => $rawColumn,
					'converter' => $converter,
				];
			} catch (\Throwable $e) {
				$this->logger->error('AirtableSchemaConverter: failed to create column', [
					'app'           => Application::APP_ID,
					'column_name'   => $columnName,
					'airtable_type' => $airtableType,
					'exception'     => $e,
				]);
				$reportRows[] = [
					'object_name'   => $columnName,
					'object_type'   => 'field',
					'airtable_type' => $airtableType,
					'reason'        => 'Column could not be created: ' . $e->getMessage(),
				];
				$columnMapping[$airtableColId] = null;
			}
		}

		return $columnMapping;
	}
}
