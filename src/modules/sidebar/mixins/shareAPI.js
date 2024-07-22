import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import '@nextcloud/dialogs/style.css'
import displayError from '../../../shared/utils/displayError.js'

export default {
	methods: {
		async getSharedWithFromBE() {
			try {
				let res
				let shares = []
				if (this.isView) {
					res = await axios.get(generateUrl('/apps/tables/share/view/' + this.activeElement.id))
					shares = shares.concat(res.data)
					res = await axios.get(generateUrl('/apps/tables/share/table/' + this.activeElement.tableId))
					return shares.concat(res.data.filter(share => share.permissionManage))
				} else {
					res = await axios.get(generateUrl('/apps/tables/share/table/' + this.activeElement.id))
					return shares.concat(res.data)
				}
			} catch (e) {
				displayError(e, t('tables', 'Could not fetch shares.'))
			}
		},

		async sendNewShareToBE(share) {
			const data = {
				nodeType: this.isView ? 'view' : 'table',
				nodeId: this.activeElement.id,
				receiver: share.user,
				receiverType: (share.isNoUser) ? 'group' : 'user',
				permissionRead: true,
				permissionCreate: true,
				permissionUpdate: true,
				permissionDelete: false,
				permissionManage: false,
			}
			try {
				await axios.post(generateUrl('/apps/tables/share'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not create share.'))
				return false
			}
			if (this.isView) await this.$store.dispatch('setViewHasShares', { viewId: this.activeElement.id, hasShares: true })
			else await this.$store.dispatch('setTableHasShares', { tableId: this.isView ? this.activeElement.tableId : this.activeElement.id, hasShares: true })
			return true
		},
		async removeShareFromBE(shareId) {
			try {
				await axios.delete(generateUrl('/apps/tables/share/' + shareId))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove share.'))
			}

		},
		async updateShareToBE(shareId, data) {
			try {
				await axios.put(generateUrl('/apps/tables/share/' + shareId + '/permission'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not update share.'))
			}
		},
	},
}
