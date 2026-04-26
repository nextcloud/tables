/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { defineConfig } from 'cypress'
import vitePreprocessor from 'cypress-vite'
import { nodePolyfills } from 'vite-plugin-node-polyfills'
import vue from '@vitejs/plugin-vue2'

const baseUrl = process.env.CYPRESS_baseUrl ?? 'http://127.0.0.1:8089/index.php/'

export default defineConfig({
	projectId: 'ixbf9n',
	e2e: {
		baseUrl,
		supportFile: 'cypress/support/e2e.js',
		setupNodeEvents(on, config) {
			on('file:preprocessor', vitePreprocessor({
				plugins: [vue(), nodePolyfills()],
				configFile: false,
			}))

			// Disable spell checking to prevent rendering differences
			on('before:browser:launch', (browser, launchOptions) => {
				if (browser.family === 'chromium' && browser.name !== 'electron') {
					launchOptions.preferences.default['browser.enable_spellchecking'] = false
					return launchOptions
				}

				if (browser.family === 'firefox') {
					launchOptions.preferences['layout.spellcheckDefault'] = 0
					return launchOptions
				}

				if (browser.name === 'electron') {
					launchOptions.preferences.spellcheck = false
					return launchOptions
				}
			})

			return config
		},
	},

	component: {
		supportFile: 'cypress/support/component.js',
		devServer: {
			framework: 'vue',
			bundler: 'vite',
			viteConfig: {
				resolve: {
					alias: {
						'vue': 'vue',
					},
				},
				plugins: [vue(), nodePolyfills()],
				optimizeDeps: {
					exclude: [
						'vite-plugin-node-polyfills/shims/buffer',
						'vite-plugin-node-polyfills/shims/global',
						'vite-plugin-node-polyfills/shims/process'
					],
					force: true
				},
				define: {
					global: 'globalThis',
				},
			},
		},
		viewportWidth: 800,
		viewportHeight: 600,
	},
})
