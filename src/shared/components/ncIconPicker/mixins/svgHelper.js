/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
export default {
	methods: {
		async getContextIcon(iconName) {
			const { default: icon } = await import(
				`./../../../../../img/material/${iconName}.svg?raw`
			)

			return icon.replaceAll(/#fff/g, 'currentColor')
		},
	},
}
