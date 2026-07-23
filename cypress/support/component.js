/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { mount } from '@cypress/vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { createPinia } from 'pinia'

import '../styleguide/global.requires.js'

// Styles necessary for rendering the component
import '../styleguide/assets/default.css'
import '../styleguide/assets/additional.css'
import '../styleguide/assets/icons.css'

const pinia = createPinia()

const prepareOptions = (options = {}) => {
	const defaultOptions = {
		global: {
			plugins: [pinia],
			mixins: [
				{ methods: { t, n } },
			],
			components: {},
			config: {
				globalProperties: {
					OC: window.OC,
					OCA: window.OCA,
				},
			},
		},
	}

	return {
		...defaultOptions,
		...options,
	}
}

Cypress.Commands.add('mount', (component, options) => {
	return mount(component, prepareOptions(options))
})

Cypress.Commands.add('reply', (route, data) => {
	cy.intercept(route, (req) => {
		req.reply(data)
	})
})
