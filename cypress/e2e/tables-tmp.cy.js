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

	it('Login as admin', () => {
		cy.login({ userId: 'admin', password: 'admin' })
		cy.visit('apps/tables')
		cy.listUsers()
	})

})
