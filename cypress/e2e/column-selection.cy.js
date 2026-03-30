/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
const columnTitle = 'single selection'
const tableTitle = 'Test number column'

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
		cy.createTable(tableTitle)
	})

	it('Insert and test rows', () => {
		cy.loadTable(tableTitle)
		cy.createSelectionColumn(columnTitle, ['first option', 'second option', '👋 third option', '🤷🏻 fifths'], 'second option', true)

		// check if default value is set on row creation
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"]').should('be.visible')
		cy.get('[data-cy="createRowModal"] .title').should('be.visible')
		cy.get('[data-cy="createRowModal"] .title').click()
		cy.get('.vs__dropdown-toggle .vs__selected span[title="second option"]').should('exist')
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="ncTable"] tr td div').contains('second option').should('be.visible')

		// create a row and select non default value
		cy.get('button').contains('Create row').click()
		cy.get('[data-cy="createRowModal"] .slot input').first().click()
		cy.get('ul.vs__dropdown-menu li span[title="👋 third option"]').click()
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="ncTable"] tr td div').contains('third option').should('be.visible')

		cy.deleteRow(0)
		cy.get('[data-cy="ncTable"] tr td div').contains('second').should('not.exist')

		// edit second row
		cy.get('[data-cy="ncTable"] [data-cy="editRowBtn"]').first().click()
		cy.get('[data-cy="editRowModal"] .slot input').first().click()
		cy.get('ul.vs__dropdown-menu li span[title="first option"]').click()
		cy.get('[data-cy="editRowSaveButton"]').click()
		cy.get('[data-cy="ncTable"] tr td div').contains('first option').should('be.visible')

		cy.deleteRow(0)

		cy.deleteTable(tableTitle)
	})

	it('Test empty selection', () => {
		cy.loadTable(tableTitle)
		cy.createSelectionColumn(columnTitle, ['first option', 'second option', '👋 third option', '🤷🏻 fifths'], null, true)

		// check if default value is set on row creation
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"]').should('be.visible')
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="ncTable"] tr td div').should('exist')
		cy.get('[data-cy="ncTable"] [data-cy="editRowBtn"]').should('exist')
	})

})
