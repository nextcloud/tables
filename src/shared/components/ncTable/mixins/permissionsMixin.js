import { getCurrentUser } from '@nextcloud/auth'

export default {
	methods: {
		// views have the flag manageTable set if the user has manage rights for the corresponding table
		canManageTable(element) {
			if (!element.isShared) {
				return true
			}
			if ((element.isShared && element?.onSharePermissions?.manageTable) || element?.ownership === getCurrentUser().uid) {
				return true
			}
			return false
		},

		canManageElement(element) {
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
			if (!element.isShared || element.ownership === getCurrentUser().uid) {
				return true
			}

			// resharing is not allowed
			return false
		},

		canDeleteData(element) {
			if (this.canManageTable(element) || this.canManageElement(element)) return true
			return element.isShared === false
				|| (element.isShared === true && (element.onSharePermissions.delete === true || element.ownership === getCurrentUser().uid))
		},
		canUpdateData(element) {
			if (this.canManageTable(element) || this.canManageElement(element)) return true
			return element.isShared === false
				|| (element.isShared === true && (element.onSharePermissions.update === true || element.ownership === getCurrentUser().uid))
		},

	},
}
