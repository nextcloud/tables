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

	it('Create a table and view and search via unified search for it', () => {
		cy.loadTable('Tutorial')
		cy.createView('asdfghjkl')
		cy.unifiedSearch('HJK')
		cy.loadTable('Tutorial')
		cy.unifiedSearch('asd')
		cy.loadTable('Tutorial')
		cy.unifiedSearch('Tutorial')
	})
})
