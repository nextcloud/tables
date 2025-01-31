/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
let targetUserTransfer

describe('Manage a table', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
		cy.createRandomUser().then(user => {
			targetUserTransfer = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Create', () => {
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('.modal__content input[type="text"]').clear().type('to do list')
		cy.get('.modal__content #description-editor .tiptap.ProseMirror').type('to Do List description')
		cy.contains('button', 'Create table').scrollIntoView().click()

		cy.contains('button', 'Create row').should('be.visible')
		cy.contains('h1', 'to do list').should('be.visible')
		cy.contains('table th', 'Task').should('exist')
		cy.contains('.text-editor__content p', 'to Do List description').should('be.visible')
	})

	it('Create with import', () => {
		cy.uploadFile('test-import.csv', 'text/csv')
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		cy.get('.tile').contains('Import').click({ force: true })
		cy.get('.modal__content input[type="text"]').clear().type('import list')
		cy.contains('button', 'Create table').scrollIntoView().click()
		cy.contains('h2', 'Import').should('be.visible')

		cy.get('.modal__content button').contains('Select from Files').click()
		cy.get('.file-picker__files').contains('test-import').click()
		cy.get('.file-picker button span').contains('Import').click()
		cy.get('.modal__content button').contains('Preview').click()
		cy.get('.file_import__preview tbody tr').should('have.length', 4)
		cy.intercept({ method: 'POST', url: '**/apps/tables/import/table/*'}).as('importUploadReq')
		cy.get('.modal__content button').contains('Import').scrollIntoView().click()
		cy.wait('@importUploadReq')
		cy.get('[data-cy="importResultColumnsFound"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '0')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '4')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
	})

	it('Update title And Description', () => {
		cy.get('.app-navigation__list').contains('to do list').click({ force: true })
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.action-button__text').contains('Edit table').click()

		cy.get('.modal-container input').last().clear().type('ToDo list')
		cy.get('.modal__content #description-editor .tiptap.ProseMirror').type('Updated ToDo List description')
		cy.get('.modal-container button').contains('Save').click()

		cy.wait(10).get('.toastify.toast-success').should('be.visible')
		cy.get('.app-navigation__list').contains('ToDo list').should('exist')
		cy.contains('.text-editor__content p', 'Updated ToDo List description').should('be.visible')
	})

	it('Delete', () => {
		cy.deleteTable('ToDo list')
	})

	it('Transfer', () => {
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('[data-cy="createTableModal"] input[type="text"]').clear().type('test table')
		cy.contains('button', 'Create table').click()

		cy.get('.app-navigation__list').contains('test table').click({ force: true })
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.action-button__text').contains('Edit table').click()

		cy.get('[data-cy="editTableModal"]').should('be.visible')
		cy.get('[data-cy="editTableModal"] button').contains('Change owner').click()
		cy.get('[data-cy="editTableModal"]').should('not.exist')
		cy.get('[data-cy="transferTableModal"]').should('be.visible')
		cy.get('[data-cy="transferTableModal"] input[type="search"]').clear().type(targetUserTransfer.userId)
		cy.get(`.vs__dropdown-menu [id="${targetUserTransfer.userId}"]`).click()
		cy.get('[data-cy="transferTableButton"]').should('be.enabled').click()
		cy.get('.toastify.toast-success').should('be.visible')
		cy.get('.app-navigation__list').contains('test table').should('not.exist')
		cy.login(targetUserTransfer)
		cy.visit('apps/tables')
		cy.get('.app-navigation__list').contains('test table')
	})
})
