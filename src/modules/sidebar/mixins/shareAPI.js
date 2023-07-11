import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import displayError from '../../../shared/utils/displayError.js'

export default {
	methods: {
		async getSharedWithFromBE() {
			try {
				const res = await axios.get(generateUrl('/apps/tables/share/view/' + this.activeView.id))
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not fetch shares.'))
			}
		},

		async sendNewShareToBE(share) {
			const data = {
				nodeType: this.getNodeType,
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
				console.debug("Try to add share: ", '/apps/tables/share', data)
				const res = await axios.post(generateUrl('/apps/tables/share'), data)
				console.debug("ANSWER:::", res, res.data, res.data.nodeId)
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
