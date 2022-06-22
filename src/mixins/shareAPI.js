import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'

export default {
	methods: {
		async getSharedWithFromBE() {
			try {
				const res = await axios.get(generateUrl('/apps/tables/share/table/' + this.activeTable.id))
				if (res.status !== 200) {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}
				// this.sharedWith = res.data.sort((a, b) => { return a.userReceiver.localeCompare(b.userReceiver) })
				return res.data
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not load shares from back end'))
			}
		},

		async sendNewShareToBE(share) {
			try {
				const data = {
					nodeType: 'table',
					nodeId: this.activeTable.id,
					receiver: share.user,
					receiverType: (share.isNoUser) ? 'group' : 'user',
					permissionRead: true,
					permissionCreate: true,
					permissionUpdate: true,
					permissionDelete: false,
					permissionManage: false,
				}
				// console.debug('data array', data)
				const res = await axios.post(generateUrl('/apps/tables/share'), data)
				if (res.status !== 200) {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
					return false
				}
				// console.debug('new share was saved', res)
				showSuccess(t('tables', 'Saved new share with "{userName}".', { userName: res.data.receiverDisplayName }))
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new share'))
			}
		},
		async removeShareFromBE(shareId) {
			try {
				const res = await axios.delete(generateUrl('/apps/tables/share/' + shareId))
				if (res.status !== 200) {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
					return false
				}
				// console.debug('Share was deleted', res)
				showSuccess(t('tables', 'Share was deleted'))
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not delete share'))
			}
		},
		async updateShareToBE(shareId, data) {
			try {
				const res = await axios.put(generateUrl('/apps/tables/share/' + shareId + '/permission'), data)
				if (res.status !== 200) {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
					return false
				}
				// console.debug('Share was deleted', res)
				showSuccess(t('tables', 'Share permission was updated.'))
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not update share permission'))
			}
		},
	},
}
