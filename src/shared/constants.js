/*
 * @copyright Copyright (c) 2024 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
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

export const SHARE_TYPES = {
	SHARE_TYPE_USER: OC.Share.SHARE_TYPE_USER,
	SHARE_TYPE_GROUP: OC.Share.SHARE_TYPE_GROUP,
	SHARE_TYPE_LINK: OC.Share.SHARE_TYPE_LINK,
	SHARE_TYPE_EMAIL: OC.Share.SHARE_TYPE_EMAIL,
	SHARE_TYPE_REMOTE: OC.Share.SHARE_TYPE_REMOTE,
	SHARE_TYPE_CIRCLE: OC.Share.SHARE_TYPE_CIRCLE,
	SHARE_TYPE_GUEST: OC.Share.SHARE_TYPE_GUEST,
	SHARE_TYPE_REMOTE_GROUP: OC.Share.SHARE_TYPE_REMOTE_GROUP,
	SHARE_TYPE_ROOM: OC.Share.SHARE_TYPE_ROOM,
}
