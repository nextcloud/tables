import { mapGetters } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	components: {
		...mapGetters(['activeTable']),
	},
	computed: {
		canManageActiveTable() {
			return this.activeTable.isShared === false
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.manage === true)
				|| (this.activeTable.isShared === true && this.activeTable.ownership === getCurrentUser().uid)
		},
		canReadDataActiveTable() {
			return this.activeTable.isShared === false
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.read === true)
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.manage === true)
				|| (this.activeTable.isShared === true && this.activeTable.ownership === getCurrentUser().uid)
		},
		canCreateDataActiveTable() {
			return this.activeTable.isShared === false
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.create === true)
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.manage === true)
				|| (this.activeTable.isShared === true && this.activeTable.ownership === getCurrentUser().uid)
		},
		canDeleteDataActiveTable() {
			return this.activeTable.isShared === false
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.delete === true)
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.manage === true)
				|| (this.activeTable.isShared === true && this.activeTable.ownership === getCurrentUser().uid)
		},
		canUpdateDataActiveTable() {
			return this.activeTable.isShared === false
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.update === true)
				|| (this.activeTable.isShared === true && this.activeTable.onSharePermissions.manage === true)
				|| (this.activeTable.isShared === true && this.activeTable.ownership === getCurrentUser().uid)
		},
	},
}
