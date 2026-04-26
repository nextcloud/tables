/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

let localUser
const contextTitle = 'test application hidden'

describe('Test context navigation hidden context', () => {
	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
		cy.get('[aria-label="Create new table"]').should('be.visible')
	})

	it('Create context that is hidden in nav by default', () => {
		cy.createContext(contextTitle, false)
		cy.visit('apps/tables')
		cy.get(`#header .app-menu-entry [title="${contextTitle}"]`).should('not.exist')

		cy.get(`[data-cy="navigationContextItem"]:contains("${contextTitle}")`).find('button').click({ force: true })
		cy.get('[data-cy="navigationContextShowInNavSwitch"] input').should('not.be.checked')
		cy.get('[data-cy="navigationContextShowInNavSwitch"] input').click({ force: true })
		cy.get('[data-cy="navigationContextShowInNavSwitch"] input').should('be.checked')
		cy.get(`#header .app-menu-entry [title="${contextTitle}"]`).should('exist')
	})
})
