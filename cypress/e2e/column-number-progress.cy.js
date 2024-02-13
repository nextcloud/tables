let localUser
const columnTitle = 'progress'
const tableTitle = 'Test number progress'

describe('Test column progress', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
			cy.visit('apps/tables')
			cy.createTable(tableTitle)
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Insert and test rows - default values', () => {
		cy.loadTable(tableTitle)
		cy.createNumberProgressColumn(columnTitle, 23, true)

		// insert default value row
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().should('contain.value', '23')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div progress').first().should('have.value', 23)

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('89')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div progress').last().should('have.value', 89)
	})

})
