/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Manage a table (Cypress supplement – create with import)', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Create with import', () => {
		cy.uploadFile('test-import.csv', 'text/csv')
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		// should type before selecting the table type tile
		cy.get('.modal__content input[type="text"]').clear().type('import list')
		cy.get('.tile').contains('Import').click({ force: true })
		cy.contains('button', 'Create table').scrollIntoView().click()
		cy.contains('h2', 'Import').should('be.visible')

		cy.get('.modal__content button').contains('Select from Files').click()
		cy.get('.file-picker__files').contains('test-import').click()
		cy.get('.file-picker button span').contains('Import').click()
		cy.intercept({ method: 'POST', url: '**/apps/tables/import-preview/**' }).as('importPreviewPath')
		cy.get('.modal__content button').contains('Preview').click()
		cy.wait('@importPreviewPath')
		cy.get('.file_import__preview tbody tr', { timeout: 20000 }).should('have.length', 4)
		cy.intercept({ method: 'POST', url: '**/apps/tables/import/table/*' }).as('importUploadReq')
		cy.get('.modal__content button').contains('Import').scrollIntoView().click()
		cy.wait('@importUploadReq')
		cy.get('[data-cy="importResultColumnsFound"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '0')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '4')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
	})
})
