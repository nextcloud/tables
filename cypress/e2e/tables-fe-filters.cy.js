/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('FE sorting and filtering', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('FE Search in table', () => {
		cy.get('.app-navigation-entry-link').contains('Welcome to Nextcloud Tables!').click({ force: true })

		// test case-sensitive
		cy.contains('Edit a row').should('exist')
		cy.get('.searchAndFilter input').type('tables')
		cy.contains('Edit a row').should('not.exist')
		cy.contains('Read the docs').should('exist')

		// test not case-sensitive
		cy.get('.searchAndFilter input').clear()
		cy.get('.searchAndFilter input').type('TABLES')
		cy.contains('Edit a row').should('not.exist')
		cy.contains('Read the docs').should('exist')

		// test search for number regarding a check field
		cy.get('.searchAndFilter input').clear()
		cy.get('.searchAndFilter input').type('3')
		cy.contains('Edit a row').should('not.exist')
		cy.contains('Read the docs').should('exist')
	})

	it('Reset FE filter on table or view change', () => {
		// create a table and view, so we can change the active table and view later on
		cy.createTable('first table')
		cy.createTextLineColumn('colA', null, null, true)

		cy.createTable('second table')
		cy.createTextLineColumn('col1', null, null, true)

		// change between tables
		cy.loadTable('first table')
		cy.sortTableColumn('colA')
		cy.get('.info').contains('Reset local adjustments').should('be.visible')
		cy.loadTable('second table')
		cy.get('.info').contains('Reset local adjustments').should('not.exist')

		// change from view to table
		cy.createView('view for second table')
		cy.sortTableColumn('col1')
		cy.get('.info').contains('Reset local adjustments').should('be.visible')
		cy.loadTable('second table')
		cy.get('.info').contains('Reset local adjustments').should('not.exist')

		// change from table to view
		cy.loadTable('first table')
		cy.sortTableColumn('colA')
		cy.get('.info').contains('Reset local adjustments').should('be.visible')
		cy.loadView('view for second table')
		cy.get('.info').contains('Reset local adjustments').should('not.exist')
	})

	it('Navigation filtering', () => {
		cy.viewport('macbook-15')
		cy.createTable('first table')
		cy.createTable('second table')
		cy.createTable('third table 🙇')
		cy.createTextLineColumn('col1', null, null, true)
		cy.createView('view for third tab')

		// all tables and views should be visible
		cy.get('[data-cy="navigationTableItem"]').contains('first table').should('be.visible')
		cy.get('[data-cy="navigationTableItem"]').contains('second table').should('be.visible')
		cy.get('[data-cy="navigationTableItem"]').contains('third table 🙇').should('be.visible')
		cy.get('[data-cy="navigationTableItem"]').contains('view for third tab').should('be.visible')

		// only tables should be visible
		cy.get('.filter-box input').clear().type('table')
		cy.get('[data-cy="navigationTableItem"]').contains('first table').should('be.visible')
		cy.get('[data-cy="navigationTableItem"]').contains('second table').should('be.visible')
		cy.get('[data-cy="navigationTableItem"]').contains('third table 🙇').should('be.visible')
		cy.get('[data-cy="navigationTableItem"]').contains('view for third tab').should('not.exist')

		// only the second table should be visible
		cy.get('.filter-box input').clear().type('second')
		cy.get('[data-cy="navigationTableItem"]').contains('first table').should('not.exist')
		cy.get('[data-cy="navigationTableItem"]').contains('second table').should('be.visible')
		cy.get('[data-cy="navigationTableItem"]').contains('third table 🙇').should('not.exist')
		cy.get('[data-cy="navigationTableItem"]').contains('view for third tab').should('not.exist')

		// only the third table and it's view should be visible
		cy.get('.filter-box input').clear().type('view for third')
		cy.get('[data-cy="navigationTableItem"]').contains('first table').should('not.exist')
		cy.get('[data-cy="navigationTableItem"]').contains('second table').should('not.exist')
		cy.get('[data-cy="navigationTableItem"]').contains('third table 🙇').should('be.visible')
		cy.get('[data-cy="navigationTableItem"]').contains('view for third tab').should('be.visible')
	})
})
