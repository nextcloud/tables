import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'

export default {
	methods: {
		async getSharedWithFromBE() {
			try {
				const res = await axios.get(generateUrl('/apps/tables/share/table/' + this.activeTable.id))
				return res.data
			} catch (e) {
				const res = e.response
				if (res.status === 401) {
					showError(t('tables', 'Could not fetch shares, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					showError(t('tables', 'Could not fetch shares, no permissions.'))
				} else if (res.status === 404) {
					showError(t('tables', 'Could not fetch shares, resource not found.'))
				} else {
					showError(t('tables', 'Could not fetch shares, unknown error.'))
				}
				console.error(e)
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
				const res = await axios.post(generateUrl('/apps/tables/share'), data)
				await this.$store.dispatch('setTableHasShares', { tableId: res.data.nodeId, hasSHares: true })
				showSuccess(t('tables', 'Saved new share with "{userName}".', { userName: res.data.receiverDisplayName }))
			} catch (e) {
				const res = e.response
				if (res.status === 401) {
					showError(t('tables', 'Could not create share, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					showError(t('tables', 'Could not create share, no permissions.'))
				} else if (res.status === 404) {
					showError(t('tables', 'Could not create share, resource not found.'))
				} else {
					showError(t('tables', 'Could not create share, unknown error.'))
				}
				console.error(e)
			}
		},
		async removeShareFromBE(shareId) {
			try {
				await axios.delete(generateUrl('/apps/tables/share/' + shareId))
				showSuccess(t('tables', 'Share was deleted'))
			} catch (e) {
				const res = e.response
				if (res.status === 401) {
					showError(t('tables', 'Could not remove share, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					showError(t('tables', 'Could not remove share, no permissions.'))
				} else if (res.status === 404) {
					showError(t('tables', 'Could not remove share, resource not found.'))
				} else {
					showError(t('tables', 'Could not remove share, unknown error.'))
				}
				console.error(e)
			}
		},
		async updateShareToBE(shareId, data) {
			try {
				await axios.put(generateUrl('/apps/tables/share/' + shareId + '/permission'), data)
				showSuccess(t('tables', 'Share permission was updated'))
			} catch (e) {
				const res = e.response
				if (res.status === 401) {
					showError(t('tables', 'Could not update share, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					showError(t('tables', 'Could not update share, no permissions.'))
				} else if (res.status === 404) {
					showError(t('tables', 'Could not update share, resource not found.'))
				} else {
					showError(t('tables', 'Could not update share, unknown error.'))
				}
				console.error(e)
			}
		},
	},
}
