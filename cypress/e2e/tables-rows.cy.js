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
		const task = `My first task ${Date.now()}`
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type(task)
		cy.get('[data-cy="createRowModal"] .ProseMirror').first().click()
		cy.get('[data-cy="createRowModal"] .ProseMirror').first().clear()
		cy.get('[data-cy="createRowModal"] .ProseMirror').first().type('My first description')
		cy.get('[data-cy="createRowModal"] [aria-label="Increase stars"]').click().click()
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('[data-cy="createRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains(task).should('exist')
	})

	it('Edit', () => {
		// Each test creates and operates on its own row so tests don't depend on
		// each other and stay retry-safe.
		const task = `Edit task ${Date.now()}`
		const changed = `Changed ${task}`
		cy.loadTable('Welcome to Nextcloud Tables!')

		// create the row to edit
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type(task)
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="createRowModal"]').should('not.exist')

		// edit it
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains(task).scrollIntoView()
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains(task).closest('[data-cy="customTableRow"]').find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowModal"] .slot input').first().clear().type(changed)
		cy.get('[data-cy="editRowModal"] [aria-label="Increase stars"]').click().click()
		cy.get('[data-cy="editRowSaveButton"]').click()

		cy.get('[data-cy="editRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains(changed).should('exist')
	})

	it('Delete', () => {
		const task = `Delete task ${Date.now()}`
		cy.loadTable('Welcome to Nextcloud Tables!')

		// create the row to delete
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type(task)
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="createRowModal"]').should('not.exist')

		// delete it
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains(task).scrollIntoView()
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains(task).closest('[data-cy="customTableRow"]').find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowDeleteButton"]').click()
		cy.get('[data-cy="editRowDeleteConfirmButton"]').click()

		cy.get('[data-cy="editRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains(task).should('not.exist')
	})

	it('Check mandatory fields error', () => {
		const todoTable = `to do list ${Date.now()}`
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		// should type before selecting the table type tile
		cy.get('[data-cy="createTableModal"] input[type="text"]').clear().type(todoTable)
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('[data-cy="createTableModal"]').should('be.visible')
		cy.get('[data-cy="createTableSubmitBtn"]').click()

		cy.loadTable(todoTable)
		cy.get('[data-cy="createRowBtn"]').click({ force: true })

		cy.get('[data-cy="createRowModal"] .notecard--error').should('exist')
		cy.get('[data-cy="createRowSaveButton"]').should('be.disabled')
		cy.get('[data-cy="createRowModal"] .slot input').first().type('My first task')
		cy.get('[data-cy="createRowModal"] .notecard--error').should('not.exist')
		cy.get('[data-cy="createRowSaveButton"]').should('be.enabled')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.loadTable(todoTable).click({ force: true })
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]').contains('My first task').closest('[data-cy="customTableRow"]').find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowModal"] .notecard--error').should('not.exist')
		cy.get('[data-cy="editRowModal"] .slot input').first().clear()
		cy.get('[data-cy="editRowModal"] .notecard--error').should('exist')
		cy.get('[data-cy="editRowSaveButton"]').should('be.disabled')

	})

	it('Inline Edit', () => {
		const task = `Test inline editing ${Date.now()}`
		const edited = `Edited inline ${Date.now()}`
		// Create a test row first
		cy.loadTable('Welcome to Nextcloud Tables!')
		cy.get('[data-cy="createRowBtn"]').click({ force: true })
		cy.get('[data-cy="createRowModal"] .slot input').first().type(task)
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="createRowModal"]').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains(task).should('exist')

		// Test inline editing by clicking the cell
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]')
			.contains(task)
			.scrollIntoView()
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"]')
			.contains(task)
			.click()

		// Verify the input field appears and is focused
		cy.get('[data-cy="ncTable"] [data-cy="customTableRow"] .cell-input input').click()
		cy.get('.cell-input input').should('be.visible')
		cy.get('.cell-input input').should('have.focus')

		// Change the content
		cy.get('.cell-input input').clear().type(`${edited}{enter}`)

		// Verify the edit was saved
		cy.get('.icon-loading-small').should('not.exist')
		cy.get('[data-cy="ncTable"] table').contains(edited).should('exist')
		cy.get('[data-cy="ncTable"] table').contains(task).should('not.exist')
	})
})
