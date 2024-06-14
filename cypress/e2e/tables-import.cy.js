let localUser

describe('Import csv', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
			cy.uploadFile('test-import.csv', 'text/csv')
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Import csv from Files', () => {
		cy.loadTable('Tutorial')
		cy.clickOnTableThreeDotMenu('Import')
		cy.get('.modal__content button').contains('Select from Files').click()
		cy.get('.file-picker__files').contains('test-import').click()
		cy.get('.file-picker button span').contains('Choose test-import.csv').click()
		cy.get('.modal__content .import-filename', { timeout: 5000 }).should('be.visible')
		cy.get('.modal__content button').contains('Preview').click()

		cy.get('.file_import__preview tbody tr').should('have.length', 4)
		cy.get('.modal__content button').contains('Import').click()

		cy.get('[data-cy="importResultColumnsFound"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
	})

	it('Import csv from device', () => {
		cy.loadTable('Tutorial')
		cy.clickOnTableThreeDotMenu('Import')
		cy.get('.modal__content button').contains('Upload from device').click()
		cy.get('input[type="file"]').selectFile('cypress/fixtures/test-import.csv', { force: true })
		cy.get('.modal__content button').contains('Preview').click()

		cy.get('.file_import__preview tbody tr', { timeout: 20000 }).should('have.length', 4)
		cy.get('.modal__content button').contains('Import').click()

		cy.get('[data-cy="importResultColumnsFound"]', { timeout: 20000 }).should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
	})

})

// only run the following tests if not testing on v26 or v27
// the files api in use is only supported on v28+
if (!['stable26', 'stable27'].includes(Cypress.env('ncVersion'))) {
	describe('Import csv from Files file action', () => {

		before(function() {
			cy.createRandomUser().then(user => {
				localUser = user
				cy.login(localUser)
				cy.uploadFile('test-import.csv', 'text/csv')
			})
		})

		beforeEach(function() {
			cy.login(localUser)
			cy.visit('apps/files/files')
		})

		it('Import to new table', () => {
			cy.get('[data-cy-files-list-row-name="test-import.csv"] [data-cy-files-list-row-actions] .action-item button').click()
			cy.get('[data-cy-files-list-row-action="import-to-tables"]').click()

			cy.intercept({ method: 'POST', url: '**/apps/tables/import/table/*'}).as('importNewTableReq')
			cy.get('[data-cy="fileActionImportButton"]').click()
			cy.wait('@importNewTableReq').its('response.statusCode').should('equal', 200)

			cy.get('[data-cy="importResultColumnsFound"]').should('contain.text', '4')
			cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '0')
			cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '4')
			cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
			cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
			cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
		})

		it('Import to existing table', () => {
			cy.get('[data-cy-files-list-row-name="test-import.csv"] [data-cy-files-list-row-actions] .action-item button').click()
			cy.get('[data-cy-files-list-row-action="import-to-tables"]').click()

			cy.get('[data-cy="importAsNewTableSwitch"]').click()
			cy.get('[data-cy="selectExistingTableDropdown"]').type('tutorial')
			cy.get('.name-parts').click()

			cy.intercept({ method: 'POST', url: '**/apps/tables/import/table/*'}).as('importExistingTableReq')
			cy.get('[data-cy="fileActionImportButton"]').click()
			cy.wait('@importExistingTableReq').its('response.statusCode').should('equal', 200)

			cy.get('[data-cy="importResultColumnsFound"]').should('contain.text', '4')
			cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '4')
			cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '0')
			cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
			cy.get('[data-cy="importResultParsingErrors"]').should('contain.text', '0')
			cy.get('[data-cy="importResultRowErrors"]').should('contain.text', '0')
		})

	})
}
