import { mapGetters } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	components: {
		...mapGetters(['activeTable', 'activeView']),
	},
	computed: {
		// TODO: Namen ändern
		activeElement() {
			if (this.activeTable) return this.activeTable
			else return this.activeView
		},
		canManageActiveTable() {
			return this.activeElement.isShared === false
				|| (this.activeElement.isShared === true && this.activeElement.onSharePermissions.manage === true)
				|| (this.activeElement.isShared === true && this.activeElement.ownership === getCurrentUser().uid)
		},
		canReadDataActiveTable() {
			return this.activeElement.isShared === false
				|| (this.activeElement.isShared === true && this.activeElement.onSharePermissions.read === true)
				|| (this.activeElement.isShared === true && this.activeElement.onSharePermissions.manage === true)
				|| (this.activeElement.isShared === true && this.activeElement.ownership === getCurrentUser().uid)
		},
		canCreateDataActiveTable() {
			return this.activeElement.isShared === false
				|| (this.activeElement.isShared === true && this.activeElement.onSharePermissions.create === true)
				|| (this.activeElement.isShared === true && this.activeElement.onSharePermissions.manage === true)
				|| (this.activeElement.isShared === true && this.activeElement.ownership === getCurrentUser().uid)
		},
		canDeleteDataActiveTable() {
			return this.activeElement.isShared === false
				|| (this.activeElement.isShared === true && this.activeElement.onSharePermissions.delete === true)
				|| (this.activeElement.isShared === true && this.activeElement.onSharePermissions.manage === true)
				|| (this.activeElement.isShared === true && this.activeElement.ownership === getCurrentUser().uid)
		},
		canUpdateDataActiveTable() {
			return this.activeElement.isShared === false
				|| (this.activeElement.isShared === true && this.activeElement.onSharePermissions.update === true)
				|| (this.activeElement.isShared === true && this.activeElement.onSharePermissions.manage === true)
				|| (this.activeElement.isShared === true && this.activeElement.ownership === getCurrentUser().uid)
		},
		canShareActiveTable() {
			if (!this.activeElement?.isShared || this.activeElement?.ownership === getCurrentUser().uid) {
				return true
			}

			// resharing is not allowed
			return false
		},
	},
}
