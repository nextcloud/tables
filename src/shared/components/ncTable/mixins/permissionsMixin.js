import { getCurrentUser } from '@nextcloud/auth'

export default {

	methods: {

		canManageTable(table) {
			if (!table.isShared) {
				return true
			}
			if ((table.isShared && table?.onSharePermissions?.manage) || table?.ownership === getCurrentUser().uid) {
				return true
			}
			return false
		},

		canReadTable(table) {
			if (!table.isShared) {
				return true
			}

			if ((table.isShared && table.onSharePermissions.read) || table?.ownership === getCurrentUser().uid) {
				return true
			}
			return false
		},

		canCreateRowInTable(table) {
			if (!table.isShared) {
				return true
			}
			if ((table.isShared && table.onSharePermissions.create) || table?.ownership === getCurrentUser().uid) {
				return true
			}
			return false
		},

		canShareTable(table) {
			if (!table.isShared || table.ownership === getCurrentUser().uid) {
				return true
			}

			// resharing is not allowed
			return false
		},

		canDeleteTable(table) {
			return this.canManageTable(table)
		},

		canDeleteData(table) {
			return table.isShared === false
				|| (table.isShared === true && table.onSharePermissions.delete === true)
				|| (table.isShared === true && table.onSharePermissions.manage === true)
				|| (table.isShared === true && table.ownership === getCurrentUser().uid)
		},

	},
}
