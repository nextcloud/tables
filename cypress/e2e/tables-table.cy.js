let localUser
let targetUserTransfer

describe('Manage a table', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
		cy.createRandomUser().then(user => {
			targetUserTransfer = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Create', () => {
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('.modal__content').should('be.visible')
		cy.get('.modal__content input[type="text"]').clear().type('to do list')
		cy.get('.modal__content #description-editor .tiptap.ProseMirror').type('to Do List description')
		cy.contains('button', 'Create table').click()

		cy.contains('button', 'Create row').should('be.visible')
		cy.contains('h1', 'to do list').should('be.visible')
		cy.contains('table th', 'Task').should('exist')
		cy.contains('.paragraph-content', 'to Do List description').should('be.visible')
	})

	it('Update title And Description', () => {
		cy.get('.app-navigation__list').contains('to do list').click({ force: true })
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.action-button__text').contains('Edit table').click()

		cy.get('.modal__content').should('be.visible')
		cy.get('.modal-container input').last().clear().type('ToDo list')
		cy.get('.modal__content #description-editor .tiptap.ProseMirror').type('Updated ToDo List description')
		cy.get('.modal-container button').contains('Save').click()

		cy.wait(10).get('.toastify.toast-success').should('be.visible')
		cy.get('.app-navigation__list').contains('ToDo list').should('exist')
		cy.contains('.paragraph-content', 'Updated ToDo List description').should('be.visible')
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

	it('Transfer', () => {
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('.modal__content').should('be.visible')
		cy.get('.modal__content input[type="text"]').clear().type('test table')
		cy.contains('button', 'Create table').click()

		cy.get('.app-navigation__list').contains('test table').click({ force: true })
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.action-button__text').contains('Edit table').click()

		cy.get('[data-cy="editTableModal"]').should('be.visible')
		cy.get('[data-cy="editTableModal"] button').contains('Change owner').click()
		cy.get('[data-cy="editTableModal"]').should('not.exist')
		cy.get('[data-cy="transferTableModal"]').should('be.visible')
		cy.get('[data-cy="transferTableModal"] input[type="search"]').clear().type(targetUserTransfer.userId)
		cy.get(`.vs__dropdown-menu [user="${targetUserTransfer.userId}"]`).click()
		cy.get('[data-cy="transferTableButton"]').should('be.enabled').click()
		cy.get('.toastify.toast-success').should('be.visible')
		cy.get('.app-navigation__list').contains('test table').should('not.exist')
		cy.login(targetUserTransfer)
		cy.visit('apps/tables')
		cy.get('.app-navigation__list').contains('test table')
	})
})
