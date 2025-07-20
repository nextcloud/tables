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
export const TYPE_NUMBER = 'number'
export const TYPE_DATETIME = 'datetime'
export const TYPE_USERGROUP = 'usergroup'
export const TYPE_TEXT_LINE = 'text-line'
export const TYPE_TEXT_LINK = 'text-link'
export const TYPE_TEXT_LONG = 'text-long'
export const TYPE_TEXT_RICH = 'text-rich'
export const TYPE_NUMBER_STARS = 'number-stars'
export const TYPE_NUMBER_PROGRESS = 'number-progress'
export const TYPE_SELECTION_MULTI = 'selection-multi'
export const TYPE_SELECTION_CHECK = 'selection-check'
export const TYPE_DATETIME_DATE = 'datetime-date'
export const TYPE_DATETIME_TIME = 'datetime-time'

export const NAV_ENTRY_MODE = {
	NAV_ENTRY_MODE_HIDDEN: 0, // no nav bar entry
	// NAV_ENTRY_MODE_RECIPIENTS: 1, // nav bar entry for share recipients, but not the owner. Currently unused.
	NAV_ENTRY_MODE_ALL: 2, // nav bar entry for everybody
}

export const ALLOWED_PROTOCOLS = ['http:', 'https:']

export const USERGROUP_TYPE = {
	USER: 0,
	GROUP: 1,
	CIRCLE: 2,
}

export const COLUMN_WIDTH_MIN = 50
export const COLUMN_WIDTH_MAX = 1000
