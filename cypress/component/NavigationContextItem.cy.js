/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { emit } from '@nextcloud/event-bus'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import NavigationContextItem from '../../src/modules/navigation/partials/NavigationContextItem.vue'

const context = { id: 3, name: 'Team board', iconName: null, owner: 'admin', ownerType: 0, sharing: {} }

/**
 * Mounts the navigation entry of the application inside a router, as the navigation does.
 */
function mountItem() {
	const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:path(.*)*', component: { render: () => null } }] })
	cy.mount(NavigationContextItem, { props: { context }, global: { plugins: [createPinia(), router], mixins: [{ methods: { t, n } }] } })
}

describe('NavigationContextItem', () => {
	it('gives the focus back to its actions once the edit dialog of the application closes', () => {
		mountItem()
		cy.get('[data-cy="navigationContextItem"] .action-item__menutoggle').should('exist')

		cy.then(() => emit('tables:context:edit:closed', 4))
		cy.focused().should('not.exist')

		cy.then(() => emit('tables:context:edit:closed', 3))
		cy.focused().should('have.class', 'action-item__menutoggle')
	})
})
