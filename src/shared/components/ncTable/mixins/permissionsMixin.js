import { getCurrentUser } from '@nextcloud/auth'

export default {

	methods: {

		canManageElement(element) {
			if (!element.isShared) {
				return true
			}
			if ((element.isShared && element?.onSharePermissions?.manage) || element?.ownership === getCurrentUser().uid) {
				return true
			}
			return false
		},

		canReadElement(element) {
			if (!element.isShared) {
				return true
			}

			if ((element.isShared && element.onSharePermissions.read) || element?.ownership === getCurrentUser().uid) {
				return true
			}
			return false
		},

		canCreateRowInElement(element) {
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

		canDeleteElement(element) {
			return this.canManageElement(element)
		},

		canDeleteData(element) {
			return element.isShared === false
				|| (element.isShared === true && element.onSharePermissions.delete === true)
				|| (element.isShared === true && element.onSharePermissions.manage === true)
				|| (element.isShared === true && element.ownership === getCurrentUser().uid)
		},

	},
}
