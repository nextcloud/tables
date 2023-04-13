import {User} from "@nextcloud/cypress"
describe('The Home Page', () => {
	it('successfully loads', () => {
		const user = new User('admin', 'admin')
		cy.login(user)
		cy.visit('apps/tables')
		cy.contains('.app-menu-entry--label', 'Tables').click()
		cy.contains('button','Create new table').click()
		cy.get('input[name="template"][value="todo"]').click({force: true})
		cy.get('.modal__content input[type="text"]').type('to do list')
		cy.contains('button','Create table').click()
		cy.contains('button','Create row').should('be.visible')
		cy.contains('h1','to do list').should('be.visible')
		cy.contains('table th', 'Task').should('be.visible')
	})
})
