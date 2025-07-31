/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { mount } from 'cypress/vue2'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { createPinia, PiniaVuePlugin } from 'pinia'
import Vue from 'vue'
Vue.use(PiniaVuePlugin)
const pinia = createPinia()

import '../styleguide/global.requires.js'

// Styles necessary for rendering the component
import '../styleguide/assets/default.css'
import '../styleguide/assets/additional.css'
import '../styleguide/assets/icons.css'

const prepareOptions = (options = {}) => {
	const defaultOptions = {
		pinia,
		extensions: {
			mixins: [
				{ methods: { t, n } },
			],
			plugins: [],
			components: {},
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
