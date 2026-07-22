/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
module.exports = {
	root: true,
	extends: [
		'@nextcloud/eslint-config/vue3',
	],
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'jsdoc/tag-lines': 'off',
		'vue/first-attribute-linebreak': 'off',
		'vue/max-attributes-per-line': 'off',
		'vue/custom-event-name-casing': ['warn', 'kebab-case'],
	},
	overrides: [
		{
			files: ['cypress/**/*.js'],
			env: {
				mocha: true,
			},
			globals: {
				cy: 'readonly',
				Cypress: 'readonly',
				expect: 'readonly',
			},
		},
	],
}
