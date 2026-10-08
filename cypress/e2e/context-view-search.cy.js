/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

let localUser
const tableName = 'fruit stock'
const viewOneTitle = 'apple view'
const viewTwoTitle = 'banana view'
const contextTitle = 'test application search scope'

describe('Test view search inside an application', () => {
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

	it('Create table with rows, two views of it and an application containing both views', () => {
		cy.createTable(tableName)
		cy.loadTable(tableName)
		cy.createTextLineColumn('Name', null, null, true)

		// two rows with distinct content so the search can discriminate
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().should('be.visible').clear().type('apple row')
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] input').first().should('be.visible').clear().type('banana row')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.createView(viewOneTitle)
		cy.loadTable(tableName)
		cy.createView(viewTwoTitle)

		cy.createContext(contextTitle, false, [viewOneTitle, viewTwoTitle])
	})

	it('Search in one view must not filter the other views', () => {
		cy.loadContext(contextTitle)
		cy.get('[data-cy="contextViewNode"]').should('have.length', 2)

		// type into the first view's search input only
		cy.get('[data-cy="contextViewNode"]:contains("' + viewOneTitle + '")')
			.find('[data-cy="elementSearchInput"] input')
			.type('apple')
		// wait for the search to be applied (debounced)
		cy.wait(700)

		// the searched view is filtered
		cy.get('[data-cy="contextViewNode"]:contains("' + viewOneTitle + '")')
			.find('td').should('contain.text', 'apple row')
			.and('not.contain.text', 'banana row')

		// the other view keeps being uninterrupted
		cy.get('[data-cy="contextViewNode"]:contains("' + viewTwoTitle + '")')
			.find('td').should('contain.text', 'apple row')
			.and('contain.text', 'banana row')

		// clearing the search un-filters the searched view
		cy.get('[data-cy="contextViewNode"]:contains("' + viewOneTitle + '")')
			.find('[data-cy="elementSearchInput"] input').clear()
		cy.wait(700)
		cy.get('[data-cy="contextViewNode"]:contains("' + viewOneTitle + '")')
			.find('td').should('contain.text', 'banana row')
	})

	it('Application-wide search filters all resources at once', () => {
		cy.loadContext(contextTitle)
		cy.get('[data-cy="contextViewNode"]').should('have.length', 2)

		cy.get('[data-cy="contextSearchInput"] input').type('apple', { force: true })
		// wait for the search to be applied (debounced)
		cy.wait(700)

		// both views are filtered
		cy.get('[data-cy="contextViewNode"]:contains("' + viewOneTitle + '")')
			.find('td').should('contain.text', 'apple row')
			.and('not.contain.text', 'banana row')
		cy.get('[data-cy="contextViewNode"]:contains("' + viewTwoTitle + '")')
			.find('td').should('contain.text', 'apple row')
			.and('not.contain.text', 'banana row')

		// clearing the search restores both views
		cy.get('[data-cy="contextSearchInput"] input').clear({ force: true })
		cy.wait(700)
		cy.get('[data-cy="contextViewNode"]:contains("' + viewTwoTitle + '")')
			.find('td').should('contain.text', 'banana row')
	})
})
