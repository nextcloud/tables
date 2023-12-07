let localUser

describe('Manage a table', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Create', () => {
		cy.contains('.app-menu-entry--label', 'Tables').click()
		cy.contains('button', 'Create new table').click()
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('.modal__content').should('be.visible')
		cy.get('.modal__content input[type="text"]').clear().type('to do list')
		cy.contains('button', 'Create table').click()

		cy.contains('button', 'Create row').should('be.visible')
		cy.contains('h1', 'to do list').should('be.visible')
		cy.contains('table th', 'Task').should('exist')
	})

	it('Update title', () => {
		cy.get('.app-navigation__list').contains('to do list').click({ force: true })
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.action-button__text').contains('Edit table').click()

		cy.get('.modal__content').should('be.visible')
		cy.get('.modal-container input').last().clear().type('ToDo list')
		cy.get('.modal-container button').contains('Save').click()

		cy.wait(10).get('.toastify.toast-success').should('be.visible')
		cy.get('.app-navigation__list').contains('ToDo list').should('exist')
	})

	it('Delete', () => {
		cy.get('.app-navigation__list').contains('ToDo list').click({ force: true })
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.action-button__text').contains('Edit table').click()

		cy.get('.modal__content').should('be.visible')
		cy.get('.modal-container button').contains('Delete').click()
		cy.get('.modal-container button').contains('I really want to delete this table!').click()

		cy.wait(10).get('.toastify.toast-success').should('be.visible')
		cy.get('.app-navigation__list').contains('to do list').should('not.exist')
	})
})
