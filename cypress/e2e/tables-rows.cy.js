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
		cy.get('[data-cy="createRowSaveButton"]').should('be.disabled')
		cy.get('[data-cy="createRowModal"] .slot input').first().type('My first task')
		cy.get('[data-cy="createRowModal"] .notecard--error').should('not.exist')
		cy.get('[data-cy="createRowSaveButton"]').should('be.enabled')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.loadTable('to do list').click({ force: true })
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('My first task').closest('[data-cy="customTableRow"]').find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowModal"] .notecard--error').should('not.exist')
		cy.get('[data-cy="editRowModal"] .slot input').first().clear()
		cy.get('[data-cy="editRowModal"] .notecard--error').should('exist')
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
		
		// Find the row and click duplicate action
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('Original row').closest('[data-cy="customTableRow"]').within(() => {
			// Click the actions menu button (three dots)
			cy.get('.action-item__menutoggle').click()
		})
		
		// Click duplicate action
		cy.get('[data-cy="duplicateRowBtn"]').click()
		
		// Verify the row was duplicated
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('Original row').should('have.length.at.least', 2)
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').should('have.length.at.least', 2)
	})

	it('Delete row using action menu', () => {
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type('Row to delete')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('Row to delete').should('exist')
		
		// Find the row and click delete action
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('Row to delete').closest('[data-cy="customTableRow"]').within(() => {
			// Click the actions menu button (three dots)
			cy.get('.action-item__menutoggle').click()
		})
		
		// Click delete action
		cy.get('[data-cy="deleteRowBtn"]').click()
		
		// Confirm deletion in dialog
		cy.get('.dialog__actions .error').contains('Delete').click()
		
		// Verify the row was deleted
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('Row to delete').should('not.exist')
	})

	it('Handle unique constraint when duplicating row', () => {
		// Create a new table with a unique column
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		cy.get('[data-cy="createTableModal"] input[type="text"]').clear().type('Unique Test Table')
		cy.get('.tile').contains('Custom').click({ force: true })
		cy.get('[data-cy="createTableModal"]').should('be.visible')
		cy.get('[data-cy="createTableSubmitBtn"]').click()

		cy.loadTable('Unique Test Table')

		// Add a unique text column
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.action-button').contains('Create column').click()
		
		// Configure the column
		cy.get('[data-cy="createColumnModal"] input').first().type('Unique Field')
		cy.get('[data-cy="createColumnModal"] .column-type').click()
		cy.get('.nc-select__option').contains('Text line').click()
		
		// Enable unique constraint
		cy.get('[data-cy="createColumnModal"]').contains('Unique value').parent().find('.checkbox-radio-switch__input').click()
		
		cy.get('[data-cy="createColumnModal"] .nc-modal__content [data-cy="createColumnSaveButton"]').click()
		cy.get('[data-cy="createColumnModal"]').should('not.exist')

		// Create a row with unique data
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type('unique-value-123')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains('unique-value-123').should('exist')
		
		// Try to duplicate the row
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('unique-value-123').closest('[data-cy="customTableRow"]').within(() => {
			cy.get('.action-item__menutoggle').click()
		})
		
		cy.get('[data-cy="duplicateRowBtn"]').click()
		
		// Verify that a new row was created but without the unique field value
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').should('have.length', 2)
		
		// The new row should exist but should not have the unique value duplicated
		cy.get('[data-cy="ncTable"] table').contains('unique-value-123').should('have.length', 1)
	})
})
