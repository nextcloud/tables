<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\AuditLog;

/**
 * Operations Tables writes to the admin audit log, in the form `app.entity.action`
 *
 * The values are matched by log consumers (filters, alert rules, SIEM imports),
 * so changing one is a breaking change for them. Case names may be renamed.
 */
enum Operation: string {
	case TableDeleted = 'tables.table.deleted';
	case TableTransferred = 'tables.table.transferred';
	case ViewDeleted = 'tables.view.deleted';
	case RowDeleted = 'tables.row.deleted';
	case ContextTransferred = 'tables.context.transferred';

	/**
	 * Short description of when the operation is logged, used for the documentation
	 */
	public function description(): string {
		return match ($this) {
			self::TableDeleted => 'A table was deleted with its views, rows and shares',
			self::TableTransferred => 'The ownership of a table was transferred to another user',
			self::ViewDeleted => 'A view was deleted with its shares',
			self::RowDeleted => 'A row was deleted',
			self::ContextTransferred => 'The ownership of an application was transferred to another user',
		};
	}
}
