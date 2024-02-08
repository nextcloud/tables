/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { defineConfig } = require('cypress')

module.exports = defineConfig({
	projectId: 'ixbf9n',

	e2e: {
		baseUrl: 'http://nextcloud.local/index.php/',
		setupNodeEvents(on, config) {
			// implement node event listeners here
		},
		pageLoadTimeout: 120000,
	},

	component: {
		devServer: {
			framework: 'vue',
			bundler: 'webpack',
		},
		viewportWidth: 800,
		viewportHeight: 600,
	},
})
