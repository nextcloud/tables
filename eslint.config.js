/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { recommendedJavascript } from '@nextcloud/eslint-config'

export default [
	{
		name: 'tables/ignores',
		ignores: ['src/types/openapi/openapi.ts'],
	},
	...recommendedJavascript,
	{
		name: 'tables/rules',
		files: ['**/*.{js,mjs,ts,vue}'],
		rules: {
			'@nextcloud/l10n-enforce-ellipsis': 'off',
			'@nextcloud/l10n-non-breaking-space': 'off',
			'@nextcloud/no-deprecated-library-props': 'off',
			'@stylistic/arrow-parens': 'off',
			'@stylistic/comma-dangle': 'off',
			'@stylistic/exp-list-style': 'off',
			'@stylistic/function-paren-newline': 'off',
			'@stylistic/implicit-arrow-linebreak': 'off',
			'@stylistic/indent': 'off',
			'@stylistic/indent-binary-ops': 'off',
			'@stylistic/max-statements-per-line': 'off',
			'@stylistic/member-delimiter-style': 'off',
			'@stylistic/operator-linebreak': 'off',
			'@stylistic/padded-blocks': 'off',
			'@stylistic/semi': 'off',
			'@typescript-eslint/consistent-type-imports': 'off',
			'antfu/top-level-function': 'off',
			'curly': 'off',
			'eqeqeq': 'off',
			'import-extensions/ban-inline-type-imports': 'off',
			'import-extensions/extensions': 'off',
			'import/no-unresolved': 'off',
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/tag-lines': 'off',
			'n/no-process-exit': 'off',
			'no-console': 'off',
			'no-constant-condition': 'off',
			'no-unused-vars': 'off',
			'no-use-before-define': 'off',
			'no-useless-assignment': 'off',
			'object-shorthand': 'off',
			'perfectionist/sort-imports': 'off',
			'perfectionist/sort-named-imports': 'off',
			'preserve-caught-error': 'off',
		},
	},
	{
		name: 'tables/vue',
		files: ['**/*.vue'],
		rules: {
			'vue/attribute-hyphenation': 'off',
			'vue/comma-dangle': 'off',
			'vue/comma-spacing': 'off',
			'vue/custom-event-name-casing': 'off',
			'vue/first-attribute-linebreak': 'off',
			'vue/max-attributes-per-line': 'off',
			'vue/multi-word-component-names': 'off',
			'vue/new-line-between-multi-line-property': 'off',
			'vue/no-boolean-default': 'off',
			'vue/no-reserved-component-names': 'off',
			'vue/no-unused-properties': 'off',
			'vue/no-unused-refs': 'off',
			'vue/padding-line-between-blocks': 'off',
			'vue/quote-props': 'off',
			'vue/space-infix-ops': 'off',
			'vue/v-on-event-hyphenation': 'off',
		},
	},
	{
		name: 'tables/cypress',
		files: ['cypress/**/*.js'],
		languageOptions: {
			globals: {
				cy: 'readonly',
				Cypress: 'readonly',
				expect: 'readonly',
				before: 'readonly',
				beforeEach: 'readonly',
				describe: 'readonly',
				it: 'readonly',
			},
		},
	},
	{
		name: 'tables/node',
		files: ['playwright/start-nextcloud-server.mjs'],
		languageOptions: {
			globals: {
				process: 'readonly',
			},
		},
	},
]
