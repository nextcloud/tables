/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Public link sharing', () => {
	let localUser
	let tableId
	const tableTitle = 'Public Share Test Table'

	before(() => {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
		})
	})

	it('Create, access and delete a public link share', () => {
		cy.login(localUser)
		cy.visit('apps/tables')

		// Create a table
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		cy.get('.modal__content input[type="text"]').clear().type(tableTitle)
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('[data-cy="createTableSubmitBtn"]').scrollIntoView().click()
		cy.loadTable(tableTitle)

		// Open share sidebar
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('[data-cy="dataTableShareBtn"]').click()
		
		cy.contains('Public links').should('be.visible')

		// Create public link

		cy.intercept('POST', '**/apps/tables/api/2/tables/*/share').as('createShare')
		cy.get('button').contains('Create public link').click()
		cy.get('.sharing-entry-link__create-form button').contains('Create').click()

		cy.wait('@createShare').then((interception) => {
			expect(interception.response.statusCode).to.eq(200)
			const shareToken = interception.response.body.ocs.data.shareToken
			expect(shareToken).to.be.a('string')

			// Access the public link
			cy.clearCookies()
			cy.visit(`apps/tables/s/${shareToken}`)

			// Verify public view
			cy.get('.public-table-view').should('be.visible')
			cy.clickOnTableThreeDotMenu('Export as CSV')
			
			// Verify we cannot edit (simple check: no edit controls or just read-only mode)
			// PublicView passes :can-edit-rows="false" etc.
			// We can check that the "Add row" button is missing.
			cy.get('[data-cy="addRowBtn"]').should('not.exist')

			// Login again to delete share
			cy.login(localUser)
			cy.visit('apps/tables')
			cy.loadTable(tableTitle)

			cy.get('[data-cy="customTableAction"] button').click()
			cy.get('[data-cy="dataTableShareBtn"]').click()

			cy.get('[data-cy="sharingEntryLinkTitle"]').should('be.visible')
			cy.get('[data-cy="sharingEntryLinkDeleteButton"]').click()

			cy.get('[data-cy="sharingEntryLinkTitle"]').should('not.exist')
		})
	})
})

