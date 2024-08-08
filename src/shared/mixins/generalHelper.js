/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'

export default {

	methods: {
		ucfirst(str) {
			if (!str) {
				return ''
			}
			// converting first letter to uppercase
			return str.charAt(0).toUpperCase() + str.slice(1)
		},

		async isUrlReachable(url) {
			if (!url) {
				return false
			}
			try {
				await axios.get(url)
				return true
			} catch (e) {
				return false
			}
		},

		hasJsonStructure(str) {
			if (typeof str !== 'string') {
				return false
			}
			try {
				const result = JSON.parse(str)
				const type = Object.prototype.toString.call(result)
				return type === '[object Object]'
					|| type === '[object Array]'
			} catch (err) {
				return false
			}
		},

	},
}
