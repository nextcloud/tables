/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const NODE_TYPE_TABLE = 0
export const NODE_TYPE_VIEW = 1

// from Application.php
export const PERMISSION_READ = 1
export const PERMISSION_CREATE = 2
export const PERMISSION_UPDATE = 4
export const PERMISSION_DELETE = 8
export const PERMISSION_MANAGE = 16
export const PERMISSION_ALL = 31

export const TYPE_META_ID = -1
export const TYPE_META_CREATED_BY = -2
export const TYPE_META_UPDATED_BY = -3
export const TYPE_META_CREATED_AT = -4
export const TYPE_META_UPDATED_AT = -5

export const TYPE_SELECTION = 'selection'
export const TYPE_TEXT = 'text'
export const TYPE_NUMBER = 'number'
export const TYPE_DATETIME = 'datetime'
export const TYPE_USERGROUP = 'usergroup'

export const NAV_ENTRY_MODE = {
	NAV_ENTRY_MODE_HIDDEN: 0,
	NAV_ENTRY_MODE_RECIPIENTS: 1,
	NAV_ENTRY_MODE_ALL: 2,
}
