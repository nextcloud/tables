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
	case TableCreated = 'tables.table.created';
	case TableArchived = 'tables.table.archived';
	case TableUnarchived = 'tables.table.unarchived';
	case TableDeleted = 'tables.table.deleted';
	case TableTransferred = 'tables.table.transferred';
	case ViewCreated = 'tables.view.created';
	case ViewUpdated = 'tables.view.updated';
	case ViewDeleted = 'tables.view.deleted';
	case RowDeleted = 'tables.row.deleted';
	case ColumnDeleted = 'tables.column.deleted';
	case ImportCompleted = 'tables.import.completed';
	case ContextCreated = 'tables.context.created';
	case ContextUpdated = 'tables.context.updated';
	case ContextDeleted = 'tables.context.deleted';
	case ContextTransferred = 'tables.context.transferred';
	case ShareCreated = 'tables.share.created';
	case SharePermissionsUpdated = 'tables.share.permissions_updated';
	case ShareDeleted = 'tables.share.deleted';
	case FederatedShareReceived = 'tables.federated_share.received';
	case LinkShareAuthenticationFailed = 'tables.link_share.authentication_failed';

	/**
	 * Short description of when the operation is logged, used for the documentation
	 */
	public function description(): string {
		return match ($this) {
			self::TableCreated => 'A table was created, from the web interface, the API, occ tables:add or a template',
			self::TableArchived => 'A table was archived for all users it is shared with',
			self::TableUnarchived => 'A table was unarchived',
			self::TableDeleted => 'A table was deleted with its views, rows and shares',
			self::TableTransferred => 'The ownership of a table was transferred to another user',
			self::ViewCreated => 'A view was created on a table',
			self::ViewUpdated => 'The filter, columns or settings of a view were changed, which changes the data people the view is shared with can see',
			self::ViewDeleted => 'A view was deleted with its shares',
			self::RowDeleted => 'A row was deleted',
			self::ColumnDeleted => 'A column was deleted with its values in all rows',
			self::ImportCompleted => 'Rows were imported into a table or view from a spreadsheet, possibly creating columns',
			self::ContextCreated => 'An application was created',
			self::ContextUpdated => 'An application was changed, including tables and views added to or removed from it, which exposes them to the people the application is shared with',
			self::ContextDeleted => 'An application was deleted with its pages and shares',
			self::ContextTransferred => 'The ownership of an application was transferred to another user',
			self::ShareCreated => 'A table, view or application was shared with a user, group, team, remote user or by public link',
			self::SharePermissionsUpdated => 'The permissions of a share of a table or view were changed',
			self::ShareDeleted => 'A share was deleted, by someone managing the shared node or through the share review',
			self::FederatedShareReceived => 'A table or view was shared with a local user from another server',
			self::LinkShareAuthenticationFailed => 'A wrong password was entered for a password protected public link share',
		};
	}
}
