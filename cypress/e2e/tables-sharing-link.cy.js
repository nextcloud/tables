/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Public link sharing', () => {
	let localUser
	const tableTitle = 'Public Share Test Table'

	before(() => {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
		})
	})

	beforeEach(() => {
		cy.login(localUser)
		cy.visit('apps/tables')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		cy.get('.modal__content input[type="text"]').clear().type(tableTitle)
		cy.get('.tile').contains('ToDo').click({ force: true })
		cy.get('[data-cy="createTableSubmitBtn"]').scrollIntoView().click()
		cy.loadTable(tableTitle)
	})

	it('Create, access and delete a public link share', () => {

		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('[data-cy="dataTableShareBtn"]').click()
		cy.contains('Public links').should('be.visible')
		cy.intercept('POST', '**/apps/tables/api/2/tables/*/share').as('createShare')
		cy.get('[data-cy="sharingEntryLinkCreateButton"]').click()
		cy.get('[data-cy="sharingEntryLinkCreateFormCreateButton"]').click()

		cy.wait('@createShare').then((interception) => {
			expect(interception.response.statusCode).to.eq(200)
			const shareToken = interception.response.body.ocs.data.shareToken
			expect(shareToken).to.be.a('string')

			cy.clearCookies()
			cy.visit(`apps/tables/s/${shareToken}`)
			cy.get('[data-cy="publicTableElement"]').should('be.visible')
			cy.clickOnTableThreeDotMenu('Export as CSV')
			
			// Verify we cannot edit (simple check: no edit controls or just read-only mode)
			// We can check that the "Add row" button is missing.
			cy.get('[data-cy="addRowBtn"]').should('not.exist')

			// Login again to delete share
			cy.login(localUser)
			cy.visit('apps/tables')
			cy.loadTable(tableTitle)

			cy.get('[data-cy="customTableAction"] button').click()
			cy.get('[data-cy="dataTableShareBtn"]').click()

			cy.get('[data-cy="sharingEntryLinkTitle"]').scrollIntoView().should('be.visible')
			cy.get('[data-cy="sharingEntryLinkDeleteButton"]').click()

			cy.get('[data-cy="sharingEntryLinkTitle"]').should('not.exist')

			// Verify share is gone
			cy.clearCookies()
			cy.visit(`apps/tables/s/${shareToken}`, { failOnStatusCode: false })
			cy.get('h2').contains('Share not found').should('be.visible')
		})
	})

	it('Create, access and delete a password protected public link share', () => {
		const password = 'extremelySafePassword123'

		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('[data-cy="dataTableShareBtn"]').click()
		cy.contains('Public links').should('be.visible')
		cy.intercept('POST', '**/apps/tables/api/2/tables/*/share').as('createShare')

		// Open create form
		cy.get('[data-cy="sharingEntryLinkCreateButton"]').click()
		
		// Set password
		cy.get('[data-cy="sharingEntryLinkPasswordCheck"]').click()
		cy.get('[data-cy="sharingEntryLinkPasswordInput"] input').type(password)

		// Create
		cy.get('[data-cy="sharingEntryLinkCreateFormCreateButton"]').click()

		cy.wait('@createShare').then((interception) => {
			expect(interception.response.statusCode).to.eq(200)
			const shareToken = interception.response.body.ocs.data.shareToken
			expect(shareToken).to.be.a('string')

			cy.clearCookies()
			cy.visit(`apps/tables/s/${shareToken}`)

			// Password Gate
			cy.get('input[type="password"]').should('be.visible').type(password)
			cy.get('button[type="submit"], input[type="submit"]').filter(':visible').first().click()
			cy.get('[data-cy="publicTableElement"]').should('be.visible')

			// Login again to delete share
			cy.login(localUser)
			cy.visit('apps/tables')
			cy.loadTable(tableTitle)

			cy.get('[data-cy="customTableAction"] button').click()
			cy.get('[data-cy="dataTableShareBtn"]').click()

			cy.get('[data-cy="sharingEntryLinkTitle"]').scrollIntoView().should('be.visible')
			cy.get('[data-cy="sharingEntryLinkDeleteButton"]').click()

			cy.get('[data-cy="sharingEntryLinkTitle"]').should('not.exist')

			// Verify share is gone
			cy.clearCookies()
			cy.visit(`apps/tables/s/${shareToken}`, { failOnStatusCode: false })
			cy.get('h2').contains('Share not found').should('be.visible')
		})
	})
})

