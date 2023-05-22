import { User } from '@nextcloud/cypress'

describe('The Home Page', () => {

	// before(function() {
	// })

	beforeEach(function() {
		const user = new User('admin', 'admin')
		cy.login(user)
		cy.visit('apps/tables')
	})

	it('successfully loads', () => {
		cy.contains('.app-menu-entry--label', 'Tables').click()
		cy.contains('button', 'Create new table').click()
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('.modal__content input[type="text"]').type('to do list')
		cy.contains('button', 'Create table').click()
		cy.contains('button', 'Create row').should('be.visible')
		cy.contains('h1', 'to do list').should('be.visible')
		cy.contains('table th', 'Task').should('be.visible')
	})

	it('open tutorial table & search', () => {
		cy.get('.app-navigation-entry-link').contains('Tutorial').click({ force: true })

		// test case sensitive
		cy.contains('Edit a row').should('be.visible')
		cy.get('.searchAndFilter input').type('tables')
		cy.contains('Edit a row').should('not.exist')
		cy.contains('Read the docs').should('be.visible')

		// test not case sensitive
		cy.get('.searchAndFilter input').clear()
		cy.get('.searchAndFilter input').type('TABLES')
		cy.contains('Edit a row').should('not.exist')
		cy.contains('Read the docs').should('be.visible')

		// test search for number regarding a check field
		cy.get('.searchAndFilter input').clear()
		cy.get('.searchAndFilter input').type('3')
		cy.contains('Edit a row').should('not.exist')
		cy.contains('Read the docs').should('be.visible')
	})
})
