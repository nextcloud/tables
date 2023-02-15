
export default {

	methods: {

		canManageTable(table) {
			if (!table.isShared) {
				return true
			}
			if (table.isShared && table.onSharePermissions.manage) {
				return true
			}
			return false
		},

		canReadTable(table) {
			if (!table.isShared) {
				return true
			}
			if (table.isShared && table.onSharePermissions.read) {
				return true
			}
			return false
		},

		canCreateRowInTable(table) {
			if (!table.isShared) {
				return true
			}
			if (table.isShared && table.onSharePermissions.create) {
				return true
			}
			return false
		},

	},
}
