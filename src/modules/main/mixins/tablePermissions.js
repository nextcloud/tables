import { mapGetters } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	computed: {
		...mapGetters(['activeView']),
		canManageActiveTable() {
			return this.activeView.isShared === false
				|| (this.activeView.isShared === true && this.activeView.onSharePermissions.manage === true)
				|| (this.activeView.isShared === true && this.activeView.ownership === getCurrentUser().uid)
		},
		canReadDataActiveTable() {
			return this.activeView.isShared === false
				|| (this.activeView.isShared === true && this.activeView.onSharePermissions.read === true)
				|| (this.activeView.isShared === true && this.activeView.onSharePermissions.manage === true)
				|| (this.activeView.isShared === true && this.activeView.ownership === getCurrentUser().uid)
		},
		canCreateDataActiveTable() {
			return this.activeView.isShared === false
				|| (this.activeView.isShared === true && this.activeView.onSharePermissions.create === true)
				|| (this.activeView.isShared === true && this.activeView.onSharePermissions.manage === true)
				|| (this.activeView.isShared === true && this.activeView.ownership === getCurrentUser().uid)
		},
		canDeleteDataActiveTable() {
			return this.activeView.isShared === false
				|| (this.activeView.isShared === true && this.activeView.onSharePermissions.delete === true)
				|| (this.activeView.isShared === true && this.activeView.onSharePermissions.manage === true)
				|| (this.activeView.isShared === true && this.activeView.ownership === getCurrentUser().uid)
		},
		canUpdateDataActiveTable() {
			return this.activeView.isShared === false
				|| (this.activeView.isShared === true && this.activeView.onSharePermissions.update === true)
				|| (this.activeView.isShared === true && this.activeView.onSharePermissions.manage === true)
				|| (this.activeView.isShared === true && this.activeView.ownership === getCurrentUser().uid)
		},
		canShareActiveTable() {
			if (!this.activeView?.isShared || this.activeView?.ownership === getCurrentUser().uid) {
				return true
			}

			// resharing is not allowed
			return false
		},
	},
}
