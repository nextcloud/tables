/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import generalHelper from '../../../mixins/generalHelper.js'

export default {

	mixins: [generalHelper],

	methods: {
		downloadFile(content, fileName) {
			const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' })
			const url = URL.createObjectURL(blob)
			const link = document.createElement('a')
			link.href = url
			link.setAttribute('download', fileName)
			document.body.appendChild(link)
			link.click()
			document.body.removeChild(link)
			URL.revokeObjectURL(url)
		},
	},
}
