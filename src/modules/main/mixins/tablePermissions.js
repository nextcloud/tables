import { mapGetters } from 'vuex'

export default {
	components: {
		...mapGetters(['activeTable']),
	},
	computed: {
		canManageActiveTable() {
			return this.activeTable.isShared === false
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.manage === true)
		},
		canReadDataActiveTable() {
			return this.activeTable.isShared === false
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.read === true)
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.manage === true)
		},
		canCreateDataActiveTable() {
			return this.activeTable.isShared === false
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.create === true)
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.manage === true)
		},
		canDeleteDataActiveTable() {
			return this.activeTable.isShared === false
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.delete === true)
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.manage === true)
		},
		canUpdateDataActiveTable() {
			return this.activeTable.isShared === false
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.update === true)
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.manage === true)
		},
	},
}
