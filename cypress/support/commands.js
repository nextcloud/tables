// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })
import { addCommands } from '@nextcloud/cypress'

addCommands()


Cypress.Commands.add('createTable', (title) => {
	cy.contains('.app-menu-entry--label', 'Tables').click()
	cy.contains('button', 'Create new table').click()
	cy.get('.tile').contains('Custom table').click({ force: true })
	cy.get('.modal__content input[type="text"]').clear().type(title)
	cy.contains('button', 'Create table').click()

	cy.contains('h1', 'Test text-link').should('be.visible')
})

Cypress.Commands.add('loadTable', (name) => {
	cy.get('.app-navigation-entry-link').contains(name).click({ force: true })
})

Cypress.Commands.add('createTextLinkColumn', (title, ressourceProvider, firstColumn) => {
	if (firstColumn) {
		cy.get('.button-vue__text').contains('Create column').click({ force: true })
	} else {
		cy.get('.customTableAction button').click()
		cy.get('.v-popper__popper li button span').contains('Create column').click({ force: true })
	}

	cy.get('.modal-container').get('input[placeholder*="Enter a column title"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Link').click({ force: true })
	// deactivate unwanted provider
	cy.get('.typeSelection span label').contains('Url').click()
	cy.get('.typeSelection span label').contains('Files').click()
	cy.get('.typeSelection span label').contains('Contacts').click()

	ressourceProvider.forEach(provider =>
		cy.get('.typeSelection span label').contains(provider).click()
	)
	cy.get('.modal-container button').contains('Save').click()

	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

