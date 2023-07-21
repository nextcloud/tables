import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import displayError from '../../../shared/utils/displayError.js'

export default {
	methods: {
		async getSharedWithFromBE() {
			try {
				let res = await axios.get(generateUrl('/apps/tables/share/view/' + this.activeView.id))
				const shares = res.data
				res = await axios.get(generateUrl('/apps/tables/share/table/' + this.activeView.tableId))
				return shares.concat(res.data)
			} catch (e) {
				displayError(e, t('tables', 'Could not fetch shares.'))
			}
		},

		async sendNewTableShareToBE(share) {
			const data = {
				nodeType: 'table',
				nodeId: this.activeView.tableId,
				receiver: share.receiver,
				receiverType: share.receiverType,
				permissionRead: false,
				permissionCreate: false,
				permissionUpdate: false,
				permissionDelete: false,
				permissionManage: true,
			}
			try {
				const res = await axios.post(generateUrl('/apps/tables/share'), data)
				showSuccess(t('tables', 'Saved new share with "{userName}".', { userName: res.data.receiverDisplayName }))
			} catch (e) {
				displayError(e, t('tables', 'Could not create share.'))
				return false
			}
			await this.$store.dispatch('setViewHasShares', { viewId: this.activeView.id, hasShares: true })
			return true
		},
		async sendNewShareToBE(share) {
			const data = {
				nodeType: 'view',
				nodeId: this.activeView.id,
				receiver: share.user,
				receiverType: (share.isNoUser) ? 'group' : 'user',
				permissionRead: true,
				permissionCreate: true,
				permissionUpdate: true,
				permissionDelete: false,
				permissionManage: false,
			}
			let viewId = null
			try {
				const res = await axios.post(generateUrl('/apps/tables/share'), data)
				viewId = res.data.nodeId
				showSuccess(t('tables', 'Saved new share with "{userName}".', { userName: res.data.receiverDisplayName }))
			} catch (e) {
				displayError(e, t('tables', 'Could not create share.'))
				return false
			}
			await this.$store.dispatch('setViewHasShares', { viewId, hasShares: true })
			return true
		},
		async removeShareFromBE(shareId) {
			try {
				await axios.delete(generateUrl('/apps/tables/share/' + shareId))
				showSuccess(t('tables', 'Share was deleted'))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove share.'))
			}

		},
		async updateShareToBE(shareId, data) {
			try {
				await axios.put(generateUrl('/apps/tables/share/' + shareId + '/permission'), data)
				showSuccess(t('tables', 'Share permission was updated'))
			} catch (e) {
				displayError(e, t('tables', 'Could not update share.'))
			}
		},
	},
}
