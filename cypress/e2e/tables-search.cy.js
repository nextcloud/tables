let localUser

describe('The Home Page', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Open tutorial table & search', () => {
		cy.get('.app-navigation-entry-link').contains('Tutorial').click({ force: true })

		// test case-sensitive
		cy.contains('Edit a row').should('exist')
		cy.get('.searchAndFilter input').type('tables')
		cy.contains('Edit a row').should('not.exist')
		cy.contains('Read the docs').should('exist')

		// test not case-sensitive
		cy.get('.searchAndFilter input').clear()
		cy.get('.searchAndFilter input').type('TABLES')
		cy.contains('Edit a row').should('not.exist')
		cy.contains('Read the docs').should('exist')

		// test search for number regarding a check field
		cy.get('.searchAndFilter input').clear()
		cy.get('.searchAndFilter input').type('3')
		cy.contains('Edit a row').should('not.exist')
		cy.contains('Read the docs').should('exist')
	})
})
