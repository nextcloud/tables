<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event|AddMissingIndicesEvent>
 */
final class AddMissingIndicesListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof AddMissingIndicesEvent) {
			return;
		}

		$event->addMissingIndex('tables_columns', 'tables_columns_t_id', ['table_id']);
		$event->addMissingIndex('tables_row_sleeves', 'tables_row_sleeves_t_id', ['table_id']);
		$event->addMissingIndex('tables_tables', 'tables_tables_ownership', ['ownership']);
	}
}
