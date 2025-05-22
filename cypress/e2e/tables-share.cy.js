/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
let localUser2
let tableTitle = 'Shared todo'

describe('Share a table', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
		cy.createRandomUser().then(user => {
			localUser2 = user
		})
	})


	it('Share table', () => {
		cy.login(localUser)
		cy.visit('apps/tables')

		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		// should type before selecting the table type tile
		cy.get('.modal__content input[type="text"]').clear().type(tableTitle)
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('[data-cy="createTableSubmitBtn"]').scrollIntoView().click()
		cy.loadTable(tableTitle)
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('[data-cy="dataTableShareBtn"]').click()
		
		cy.intercept({ method: 'GET', url: `**/autocomplete/get?search=${localUser2.userId}&**` }).as('userSearch')
		cy.get('[data-cy="shareFormSelect"] input').type(localUser2.userId)
		cy.wait('@userSearch')
		cy.get(`.vs__dropdown-menu [user="${localUser2.userId}"]`).click()
		cy.wait(1000)
		cy.get('[data-cy="sharedWithList"]').contains(localUser2.userId).should('exist')

		cy.login(localUser2)
		cy.visit('apps/tables')
		cy.get('[data-cy="navigationTableItem"]').contains(tableTitle).should('exist')
	})
})
