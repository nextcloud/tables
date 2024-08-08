/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { mount } from 'cypress/vue2'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import store from '../../src/store/store.js'
import data from '../../src/store/data.js'

// Styles necessary for rendering the component
import '../styleguide/assets/default.css'
import '../styleguide/assets/additional.css'
import '../styleguide/assets/icons.css'

const prepareOptions = (options = {}) => {
	store.data = data

	const defaultOptions = {
		extensions: {
			mixins: [
				{ methods: { t, n } },
				{ store, },
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
