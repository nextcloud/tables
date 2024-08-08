/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
const columnTitle = 'stars'
const tableTitle = 'Test number stars'

describe('Test column stars', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Table and column setup', () => {
		cy.createTable(tableTitle)
	})

	it('Insert and test rows - default values', () => {
		cy.loadTable(tableTitle)
		cy.createNumberStarsColumn(columnTitle, 2, true)

		// insert default value row
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content').contains('★★☆☆☆').should('be.visible')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('★★☆☆☆').should('be.visible')

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('.slot button').last().click().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('★★★★☆').should('be.visible')

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('.slot button').first().click().click()
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('☆☆☆☆☆').should('be.visible')
	})

})
