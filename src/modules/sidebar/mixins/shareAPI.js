/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import '@nextcloud/dialogs/style.css'
import displayError from '../../../shared/utils/displayError.js'
import ShareTypes from '../../../shared/mixins/shareTypesMixin.js'
import { useTablesStore } from '../../../store/store.js'
import { mapActions } from 'pinia'

export default {
	mixins: [ShareTypes],
	data() {
		return {
			tablesStore: useTablesStore(),
		}
	},

	methods: {
		...mapActions('tables', ['setTableHasShares', 'setViewHasShares']),
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
				console.error('Error fetching shares:', e)
				displayError(e, t('tables', 'Could not fetch shares.'))
				return []
			}
		},

		async sendNewShareToBE(share) {
			if (!this.isValidShareType(share.shareType)) {
				console.warn('Unsupported share type:', share.shareType)
				return false
			}

			const data = {
				nodeType: this.isView ? 'view' : 'table',
				nodeId: this.activeElement.id,
				receiver: share.user,
				receiverType: this.getReceiverType(share.shareType),
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

			if (this.isView) {
				await this.setViewHasShares({
					viewId: this.activeElement.id,
					hasShares: true,
				})
			} else {
				await this.setTableHasShares({
					tableId: this.isView ? this.activeElement.tableId : this.activeElement.id,
					hasShares: true,
				})
			}
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

		isValidShareType(shareType) {
			if (shareType === this.SHARE_TYPES.SHARE_TYPE_CIRCLE && !this.isCirclesEnabled) {
				return false
			}
			return [
				this.SHARE_TYPES.SHARE_TYPE_USER,
				this.SHARE_TYPES.SHARE_TYPE_GROUP,
				...(this.isCirclesEnabled ? [this.SHARE_TYPES.SHARE_TYPE_CIRCLE] : []),
			].includes(shareType)
		},

		getReceiverType(shareType) {
			switch (shareType) {
			case this.SHARE_TYPES.SHARE_TYPE_USER:
				return 'user'
			case this.SHARE_TYPES.SHARE_TYPE_GROUP:
				return 'group'
			case this.SHARE_TYPES.SHARE_TYPE_CIRCLE:
				return 'circle'
			default:
				throw new Error('Invalid share type')
			}
		},
	},
}
