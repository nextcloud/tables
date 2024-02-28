let localUser

describe('Import csv', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Import csv from Files', () => {
		cy.uploadFile('test-import.csv', 'text/csv')
		cy.loadTable('Tutorial')
		cy.clickOnTableThreeDotMenu('Import')
		cy.get('.modal__content button').contains('Select from Files').click()
		cy.get('.file-picker__files').contains('test-import').click()
		cy.get('.file-picker button span').contains('Choose test-import.csv').click()
		cy.get('.modal__content button').contains('Import').click()
		cy.get('[data-cy="importResultColumnsFound"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('not.exist')
		cy.get('[data-cy="importResultRowErrors"]').should('not.exist')
	})

	it('Import csv from device', () => {
		cy.loadTable('Tutorial')
		cy.clickOnTableThreeDotMenu('Import')
		cy.get('.modal__content button').contains('Upload from device').click()
		cy.get('input[type="file"]').selectFile('cypress/fixtures/test-import.csv', { force: true })
		cy.get('.modal__content button').contains('Import').click()
		cy.get('[data-cy="importResultColumnsFound"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsMatch"]').should('contain.text', '4')
		cy.get('[data-cy="importResultColumnsCreated"]').should('contain.text', '0')
		cy.get('[data-cy="importResultRowsInserted"]').should('contain.text', '3')
		cy.get('[data-cy="importResultParsingErrors"]').should('not.exist')
		cy.get('[data-cy="importResultRowErrors"]').should('not.exist')
	})

})
