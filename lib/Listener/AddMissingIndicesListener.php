<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IDBConnection;

/**
 * @template-implements IEventListener<Event|AddMissingIndicesEvent>
 */
final class AddMissingIndicesListener implements IEventListener {
	/**
	 * Indices on the values of cell tables that store them as text, by table. Oracle stores these
	 * values as CLOB, which it cannot index, and MySQL indexes only a prefix of them.
	 */
	public const TEXT_VALUE_INDICES = [
		'tables_row_cells_selection' => 'tables_row_cells_selection_cv',
		'tables_row_cells_datetime' => 'tables_row_cells_datetime_cv',
		'tables_row_cells_usergroup' => 'tables_row_cells_usergroup_cv',
	];

	/**
	 * Covers selection option ids, dates and user, group and team ids entirely.
	 */
	public const TEXT_VALUE_PREFIX_LENGTH = 64;

	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AddMissingIndicesEvent) {
			return;
		}

		$event->addMissingIndex('tables_columns', 'tables_columns_t_id', ['table_id']);
		$event->addMissingIndex('tables_row_sleeves', 'tables_row_sleeves_t_id', ['table_id']);
		$event->addMissingIndex('tables_tables', 'tables_tables_ownership', ['ownership']);
		$event->addMissingIndex('tables_row_cells_number', 'tables_row_cells_number_cv', ['column_id', 'value']);

		if ($this->connection->getDatabaseProvider() === IDBConnection::PLATFORM_ORACLE) {
			return;
		}
		foreach (self::TEXT_VALUE_INDICES as $tableName => $indexName) {
			$event->addMissingIndex($tableName, $indexName, ['column_id', 'value'], ['lengths' => [null, self::TEXT_VALUE_PREFIX_LENGTH]]);
		}
	}
}
