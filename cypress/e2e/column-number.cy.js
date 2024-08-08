/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
const columnTitle = 'num1'
const tableTitle = 'Test number column'

describe('Test column number', () => {

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

	it('Insert and test rows - default values', () => {
		cy.loadTable(tableTitle)
		cy.createNumberColumn(columnTitle, null, null, null, null, null, null, true)

		// insert row with int value
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('21')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('21.00').should('be.visible')

		// delete row
		cy.get('.NcTable tr td button').first().click()
		cy.get('button').contains('Delete').click()
		cy.get('button').contains('I really').click()

		// insert row with float value
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('21.305')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('21.30').should('be.visible')

		// delete row
		cy.get('.NcTable tr td button').first().click()
		cy.get('button').contains('Delete').click()
		cy.get('button').contains('I really').click()

		// insert row with float value with wrong format
		// can not be tested due to: https://github.com/cypress-io/cypress/issues/7775
		/*
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('21,4')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('21.40').should('be.visible')
		*/
		cy.removeColumn(columnTitle)
	})

	it('Insert and test rows - individual column settings', () => {
		cy.loadTable('Test number column')
		cy.createNumberColumn(columnTitle, 3.5, 1, 2, 20, 'PRE', 'SUF', true)

		// insert row with default values
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().should('contain.value', '3.5')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('PRE3.5SUF').should('be.visible')

		// insert row with too high number
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('100')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('PRE20.0SUF').should('be.visible')

		// insert row with too low number
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('-1')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('PRE2.0SUF').should('be.visible')
	})

})
