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

const url = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')

addCommands()

Cypress.Commands.add('createTable', (title) => {
	cy.contains('.app-menu-entry--label', 'Tables').click()
	cy.contains('button', 'Create new table').click()
	cy.get('.tile').contains('Custom table').click({ force: true })
	cy.get('.modal__content input[type="text"]').clear().type(title)
	cy.contains('button', 'Create table').click()

	cy.contains('h1', title).should('be.visible')
})

Cypress.Commands.add('createView', (title) => {
	cy.get('[data-cy="customTableAction"] button').click()
	cy.get('.v-popper__popper li button span').contains('Create view').click({ force: true })

	cy.get('.modal-container #settings-section_title input').type(title)

	cy.contains('button', 'Create View').click()

	cy.contains('.app-navigation-entry-link span', title).should('exist')
})

Cypress.Commands.add('clickOnTableThreeDotMenu', (optionName) => {
	cy.get('[data-cy="customTableAction"] button').click()
	cy.get('.v-popper__popper li button span').contains(optionName).click({ force: true })
})

Cypress.Commands.add('loadTable', (name) => {
	cy.get('.app-navigation-entry-link').contains(name).click({ force: true })
})

Cypress.Commands.add('unifiedSearch', (term) => {
	cy.get('#unified-search').click()
	cy.get('#unified-search__input').type(term)
	cy.get('.unified-search__results .unified-search__result-line-one span').contains(term, { matchCase: false }).should('exist')
})

Cypress.Commands.add('createTextLinkColumn', (title, ressourceProvider, firstColumn) => {
	if (firstColumn) {
		cy.get('.button-vue__text').contains('Create column').click({ force: true })
	} else {
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Create column').click({ force: true })
	}

	cy.get('.modal-container').get('input[placeholder*="Enter a column title"]').clear().type(title)
	cy.get('.columnTypeSelection .vs__open-indicator').click({ force: true })
	cy.get('.multiSelectOptionLabel').contains('Link').click({ force: true })
	// deactivate unwanted provider
	cy.get('.typeSelection span label').contains('Url', { matchCase: false }).click()
	cy.get('.typeSelection span label').contains('Files').click()
	cy.get('.typeSelection span label').contains('Contacts').click()

	ressourceProvider.forEach(provider =>
		cy.get('.typeSelection span label').contains(provider, { matchCase: false }).click()
	)
	cy.get('.modal-container button').contains('Save').click()

	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('createTextLineColumn', (title, firstColumn) => {
	if (firstColumn) {
		cy.get('.button-vue__text').contains('Create column').click({ force: true })
	} else {
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Create column').click({ force: true })
	}

	cy.get('.modal-container').get('input[placeholder*="Enter a column title"]').clear().type(title)
	cy.get('.modal-container button').contains('Save').click()

	cy.wait(10).get('.toastify.toast-success').should('be.visible')
	cy.get('.custom-table table tr th .cell').contains(title).should('exist')
})

Cypress.Commands.add('uploadFile', (fileName, mimeType, target) => {
	return cy.fixture(fileName, 'binary')
		.then(Cypress.Blob.binaryStringToBlob)
		.then(blob => {
			if (typeof target !== 'undefined') {
				fileName = target
			}
			cy.request('/csrftoken')
				.then(({ body }) => {
					return cy.wrap(body.token)
				})
				.then(async (requesttoken) => {
					return cy.request({
						url: `${url}/remote.php/webdav/${fileName}`,
						method: 'put',
						body: blob.size > 0 ? blob : '',
						// auth,
						headers: {
							requesttoken,
							'Content-Type': mimeType,
						},
					})
				}).then(response => {
					const fileId = Number(
						response.headers['oc-fileid']?.split('oc')?.[0],
					)
					cy.log(`Uploaded ${fileName}`,
						response.status,
						{ fileId },
					)
					return cy.wrap(fileId)
				})
		})
})
