/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Rows for a table', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Create', () => {
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type('My first task')
		cy.get('[data-cy="createRowModal"] .ProseMirror').first().click()
		cy.get('[data-cy="createRowModal"] .ProseMirror').first().clear()
		cy.get('[data-cy="createRowModal"] .ProseMirror').first().type('My first description')
		cy.get('[data-cy="createRowModal"] [aria-label="Increase stars"]').click().click()
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('My first task').should('exist')
	})

	it('Edit', () => {
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('My first task').closest('[data-cy="customTableRow"]').find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowModal"] .slot input').first().clear().type('Changed column value')
		cy.get('[data-cy="editRowModal"] [aria-label="Increase stars"]').click().click()
		cy.get('[data-cy="editRowSaveButton"]').click()

		cy.get('[data-cy="editRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('Changed column value').should('exist')
	})

	it('Delete', () => {
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('Changed column value').closest('[data-cy="customTableRow"]').find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowDeleteButton"]').click()
		cy.get('[data-cy="editRowDeleteConfirmButton"]').click()

		cy.get('[data-cy="editRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('Changed column value').should('not.exist')
	})

	it('Check mandatory fields error', () => {
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		// should type before selecting the table type tile
		cy.get('[data-cy="createTableModal"] input[type="text"]').clear().type('to do list')
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('[data-cy="createTableModal"]').should('be.visible')
		cy.get('[data-cy="createTableSubmitBtn"]').click()

		cy.loadTable('to do list')
		cy.get('[data-cy="createRowBtn"]').click({ force: true })

		cy.get('[data-cy="createRowModal"] .notecard--error').should('exist')
		cy.wait(500)
		cy.get('[data-cy="createRowSaveButton"]').should('be.disabled')
		cy.get('[data-cy="createRowModal"] .slot input').first().type('My first task')
		cy.get('[data-cy="createRowModal"] .notecard--error').should('not.exist')
		cy.get('[data-cy="createRowSaveButton"]').should('be.enabled')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.loadTable('to do list').click({ force: true })
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('My first task').closest('[data-cy="customTableRow"]').find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowModal"] .notecard--error').should('not.exist')
		cy.get('[data-cy="editRowModal"] .slot input').first().clear()
		//cy.get('[data-cy="editRowModal"] .notecard--error').should('exist')
		cy.get('[data-cy="editRowSaveButton"]').should('be.disabled')

	})

	it('Inline Edit', () => {
		// Create a test row first
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type('Test inline editing')
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="createRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('Test inline editing').should('exist')
		
		// Test inline editing by double-clicking the cell
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]')
			.contains('Test inline editing')
			.click()
		
		// Verify the input field appears and is focused
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"] .cell-input input').click()
		cy.get('.cell-input input').should('be.visible')
		cy.get('.cell-input input').should('have.focus')
		
		// Change the content
		cy.get('.cell-input input').clear().type('Edited inline{enter}')
		
		// Verify the edit was saved
		cy.get('.icon-loading-small').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('Edited inline').should('exist')
		cy.get('[data-cy="ncTable"] table').contains('Test inline editing').should('not.exist')
	})

	it('Duplicate row using action menu', () => {
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type('Original row')
		cy.get('[data-cy="createRowModal"] .ProseMirror').first().click()
		cy.get('[data-cy="createRowModal"] .ProseMirror').first().clear()
		cy.get('[data-cy="createRowModal"] .ProseMirror').first().type('Original description')
		cy.get('[data-cy="createRowModal"] [aria-label="Increase stars"]').click().click()
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('Original row').should('exist')
		
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('Original row').closest('[data-cy="customTableRow"]').within(() => {
			cy.get('[data-cy="tableRowActions"]').click()
		})
		cy.get('[data-cy="duplicateRowBtn"]').click()
		
		cy.get('.icon-loading').should('not.exist')
		cy.get('.toastify.toast-success').should('be.visible')
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').should('have.length.at.least', 2)
	})

	it('Delete row using action menu', () => {
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type('Row to delete')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('Row to delete').should('exist')
		
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('Row to delete').closest('[data-cy="customTableRow"]').within(() => {
			cy.get('[data-cy="tableRowActions"]').click()
		})
		cy.get('[data-cy="deleteRowBtn"]').click()
		cy.get('[data-cy="deleteRowsConfirmation"] button').contains('Confirm').click()
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('Row to delete').should('not.exist')
	})

	it('Handle unique constraint when duplicating row', () => {
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		cy.get('[data-cy="createTableModal"] input[type="text"]').clear().type('Unique Test Table')
		cy.get('.tile').contains('Custom').click({ force: true })
		cy.get('[data-cy="createTableModal"]').should('be.visible')
		cy.get('[data-cy="createTableSubmitBtn"]').click()

		cy.loadTable('Unique Test Table')

		// Add a unique text column
		cy.createTextLineColumn('Unique Text', '', '20', true, true)

		// Create a row with unique data
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type('unique-value-123')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('unique-value-123').should('exist')

		// Try to duplicate the row
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('unique-value-123').closest('[data-cy="customTableRow"]').within(() => {
			cy.get('[data-cy="tableRowActions"]').click()
		})
		
		cy.get('[data-cy="duplicateRowBtn"]').click()
		
		// Verify that cloning fails due to unique constraint
		cy.get('.toastify.toast-error').should('be.visible').and('contain', 'Could not duplicate row')
	})
})
