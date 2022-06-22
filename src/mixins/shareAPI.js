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
					user: share.user,
				}
				console.debug('data array', data)
				const res = await axios.post(generateUrl('/apps/tables/share'), data)
				if (res.status !== 200) {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
					return false
				}
				console.debug('new share was saved', res)
				showSuccess(t('tables', 'Saved new share with "{userName}".', { userName: share.user }))
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new share'))
			}
		},
	},
}
