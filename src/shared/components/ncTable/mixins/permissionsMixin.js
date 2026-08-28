/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { getCurrentUser } from '@nextcloud/auth'

export default {
	methods: {
		ownsTable(element) {
			return element?.ownership === getCurrentUser().uid
		},
		// views have the flag manageTable set if the user has manage rights for the corresponding table
		canManageTable(element) {
			if (!element) {
				return false
			}
			if (!element.isShared) {
				return true
			}
			if ((element.isShared && element?.onSharePermissions?.manage) || element?.ownership === getCurrentUser().uid) {
				return true
			}
			return false
		},

		canManageElement(element) {
			if (!element) {
				return false
			}
			if (this.canManageTable(element)) return true
			if (!element.isShared) {
				return true
			}
			if ((element.isShared && element?.onSharePermissions?.manage) || element?.ownership === getCurrentUser().uid) {
				return true
			}
			return false
		},

		canReadData(element) {
			if (!element) {
				return false
			}
			if (this.canManageTable(element) || this.canDeleteData(element) || this.canUpdateData(element) || this.canManageElement(element)) return true
			if (!element.isShared) {
				return true
			}

			if ((element.isShared && element.onSharePermissions.read) || element?.ownership === getCurrentUser().uid) {
				return true
			}
			return false
		},

		canCreateRowInElement(element) {
			if (!element) {
				return false
			}
			if (this.canManageTable(element) || this.canManageElement(element)) return true
			if (!element.isShared) {
				return true
			}
			if ((element.isShared && element.onSharePermissions.create) || element?.ownership === getCurrentUser().uid) {
				return true
			}
			return false
		},

		canShareElement(element) {
			if (!element) {
				return false
			}
			if (!element.isShared || element.ownership === getCurrentUser().uid || this.canManageElement(element)) {
				return true
			}

			return false
		},

		canDeleteData(element) {
			if (!element) {
				return false
			}
			if (this.canManageTable(element) || this.canManageElement(element)) return true
			return element.isShared === false
				|| (element.isShared === true && (element.onSharePermissions.delete === true || element.ownership === getCurrentUser().uid))
		},
		canUpdateData(element) {
			if (!element) {
				return false
			}
			if (this.canManageTable(element) || this.canManageElement(element)) return true
			return element.isShared === false
				|| (element.isShared === true && (element.onSharePermissions.update === true || element.ownership === getCurrentUser().uid))
		},
		ownsContext(context) {
			return context?.owner === getCurrentUser().uid
		},

	},
}
