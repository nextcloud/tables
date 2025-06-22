/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
const columnTitle = 'check'
const tableTitle = 'Test selection check'

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

	it('Insert and test rows - default value unchecked', () => {
		cy.loadTable(tableTitle)
		cy.createSelectionCheckColumn(columnTitle, null, true)

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div .checkbox-radio-switch--checked').should('not.exist')

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('[data-cy="selectionCheckFormSwitch"]').first().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div .checkbox-radio-switch--checked').should('be.visible')

		cy.removeColumn(columnTitle)
	})

	it('Insert and test rows - default value checked', () => {
		cy.loadTable(tableTitle)
		cy.createSelectionCheckColumn(columnTitle, true, true)

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div .checkbox-radio-switch--checkedn').should('not.exist')

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('[data-cy="selectionCheckFormSwitch"]').first().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div .checkbox-radio-switch--checked').should('be.visible')

		cy.removeColumn(columnTitle)
	})

})
