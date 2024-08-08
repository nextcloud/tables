/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
const columnTitle = 'date and time'
const tableTitle = 'Test datetime'

describe('Test column ' + columnTitle, () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Table and column setup', () => {
		cy.createTable(tableTitle)
	})

	it('Insert and test rows', () => {
		cy.loadTable(tableTitle)
		cy.createDatetimeColumn(columnTitle, null, true)

		// insert row with int value
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('2023-12-24T05:15')
		cy.get('[data-cy="createRowAddMoreSwitch"]').click().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('24').should('be.visible')
		cy.get('.custom-table table tr td div').contains('Dec').should('be.visible')
		cy.get('.custom-table table tr td div').contains('2023').should('be.visible')
		cy.get('.custom-table table tr td div').contains('5:15').should('be.visible')

		// delete row
		cy.get('.NcTable tr td button').first().click()
		cy.get('button').contains('Delete').click()
		cy.get('button').contains('I really').click()

		cy.removeColumn(columnTitle)
	})

	it('Insert and test rows - default now', () => {
		const now = new Date(2023, 11, 24, 7, 21)
		cy.clock(now)
		cy.loadTable(tableTitle)
		cy.createDatetimeColumn(columnTitle, true, true)

		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().should('contain.value', '2023-12-24T07:21')
		cy.get('[data-cy="createRowAddMoreSwitch"]').click().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('7:').should('be.visible')
		cy.get('.custom-table table tr td div').contains('Dec').should('be.visible')
		cy.get('.custom-table table tr td div').contains('2023').should('be.visible')
		cy.get('.custom-table table tr td div').contains(':21').should('be.visible')
	})

})
