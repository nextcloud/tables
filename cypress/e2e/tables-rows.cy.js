let localUser

describe('Rows for a table', () => {

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
		cy.get('.app-navigation-entry-link').contains('Tutorial').click({ force: true })
		cy.get('.NcTable').contains('Create row').click({ force: true })
		cy.get('.modal__content .slot input').first().type('My first task')
		cy.get('.modal__content .ProseMirror').first().click()
		cy.get('.modal__content .ProseMirror').first().clear()
		cy.get('.modal__content .ProseMirror').first().type('My first description')
		cy.get('.modal__content [aria-label="Increase stars"]').click().click()
		cy.get('.modal-container button').contains('Save').click()

		cy.get('.modal-container:visible').should('not.exist')
		cy.get('.custom-table table').contains('My first task').should('exist')
	})

	it('Edit', () => {
		cy.get('.app-navigation-entry-link').contains('Tutorial').click({ force: true })
		cy.get('.custom-table table').contains('My first task').parent().parent().find('[aria-label="Edit row"]').click()
		cy.get('.modal__content .slot input').first().clear().type('Changed column value')
		cy.get('.modal__content [aria-label="Increase stars"]').click().click()
		cy.get('.modal-container button').contains('Save').click()

		cy.get('.modal-container:visible').should('not.exist')
		cy.get('.custom-table table').contains('Changed column value').should('exist')
	})

	it('Delete', () => {
		cy.get('.app-navigation-entry-link').contains('Tutorial').click({ force: true })
		cy.get('.custom-table table').contains('Changed column value').parent().parent().find('[aria-label="Edit row"]').click()
		cy.get('.modal-container button').contains('Delete').click()
		cy.get('.modal-container button').contains('I really want to delete this row!').click()

		cy.get('.modal-container:visible').should('not.exist')
		cy.get('.custom-table table').contains('Changed column value').should('not.exist')
	})

	it('Check mandatory fields error', () => {
		cy.contains('.app-menu-entry--label', 'Tables').click()
		cy.contains('button', 'Create new table').click()
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('.modal__content').should('be.visible')
		cy.get('.modal__content input[type="text"]').clear().type('to do list')
		cy.contains('button', 'Create table').click()

		cy.get('.app-navigation-entry-link').contains('to do list').click({ force: true })
		cy.get('.NcTable').contains('Create row').click({ force: true })

		cy.get('[data-cy="createRowModal"] .notecard--error').should('exist')
		cy.get('[data-cy="createRowSaveButton"]').should('be.disabled')
		cy.get('.modal__content .slot input').first().type('My first task')
		cy.get('[data-cy="createRowModal"] .notecard--error').should('not.exist')
		cy.get('[data-cy="createRowSaveButton"]').should('be.enabled')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.get('.app-navigation-entry-link').contains('to do list').click({ force: true })
		cy.get('.custom-table table').contains('My first task').parent().parent().find('[aria-label="Edit row"]').click()
		cy.get('[data-cy="editRowModal"] .notecard--error').should('not.exist')
		cy.get('.modal__content .slot input').first().clear()
		cy.get('[data-cy="editRowModal"] .notecard--error').should('exist')
		cy.get('[data-cy="editRowSaveButton"]').should('be.disabled')

	})
})
