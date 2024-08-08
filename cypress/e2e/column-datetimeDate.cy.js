/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
const columnTitle = 'date'
const tableTitle = 'Test datetime date'

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
		cy.createDatetimeDateColumn(columnTitle, null, true)

		// insert row with int value
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('2023-12-24')
		cy.get('[data-cy="createRowAddMoreSwitch"]').click().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('24').should('be.visible')
		cy.get('.custom-table table tr td div').contains('Dec').should('be.visible')
		cy.get('.custom-table table tr td div').contains('2023').should('be.visible')

		// delete row
		cy.get('.NcTable tr td button').first().click()
		cy.get('button').contains('Delete').click()
		cy.get('button').contains('I really').click()

		cy.removeColumn(columnTitle)
	})

	it('Insert and test rows - default now', () => {
		cy.loadTable(tableTitle)
		cy.createDatetimeDateColumn(columnTitle, true, true)

		// insert row with int value
		cy.get('button').contains('Create row').click()
		const date = new Date().toISOString().slice(2, 10)
		cy.get('.modal__content input').first().should('contain.value', date)
		cy.get('[data-cy="createRowAddMoreSwitch"]').click().click()
		cy.get('button').contains('Save').click()
		const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
		const datetime2 = new Date().getDate() + ' ' + monthNames[new Date().getMonth()] + ' ' + new Date().getFullYear()
		cy.log(datetime2)
		cy.get('.custom-table table tr td div').contains(new Date().getDate()).should('be.visible')
		cy.get('.custom-table table tr td div').contains(monthNames[new Date().getMonth()]).should('be.visible')
		cy.get('.custom-table table tr td div').contains(new Date().getFullYear()).should('be.visible')
	})

})
