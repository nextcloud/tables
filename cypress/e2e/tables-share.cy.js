let localUser
let localUser2

describe('Manage a table', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
		cy.createRandomUser().then(user => {
			localUser2 = user
		})
	})

	beforeEach(function() {
	})

	it('Share table', () => {
		cy.login(localUser)
		cy.visit('apps/tables')

		// create table to share
		cy.contains('.app-menu-entry--label', 'Tables').click()
		cy.contains('button', 'Create new table').click()
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('.modal__content input[type="text"]').clear().type('Shared todo')
		cy.contains('button', 'Create table').click()

		cy.get('.app-navigation-entry-link').contains('Shared todo').click({ force: true })
		cy.get('.NcTable table tr th').last().find('button').click({ force: true })
		cy.get('.v-popper__popper.v-popper--theme-dropdown.action-item__popper.v-popper__popper--shown').contains('Share').click({ force: true })
		cy.get('.sharing input').type(localUser2.userId)
		cy.wait(1000).get('.sharing input').type('{enter}')

		cy.wait(10).get('.toastify.toast-success').should('be.visible')
		cy.get('h3').contains('Shares').parent().find('ul').contains(localUser2.userId).should('exist')
	})

	it('Check for shared table', () => {
		cy.login(localUser2)
		cy.visit('apps/tables')
		cy.get('.app-navigation-entry-link').contains('Shared todo').should('exist')
	})
})
