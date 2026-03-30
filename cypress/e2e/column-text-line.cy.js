/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Test column text line', () => {

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
		cy.createTable('Test text line column')
		cy.loadTable('Test text line column')

		cy.createTextLineColumn('text line', 'test', '12', true)
	})

	it('Insert and test rows', () => {
		cy.loadTable('Test text line column')

		// check if default value is set on row creation
		cy.get('button').contains('Create row').click()
		cy.get('.modal-container__content h2').contains('Create row').should('be.visible')
		cy.get('.modal__content .title').contains('text line').should('be.visible')
		cy.get('.modal__content input').first().should('be.visible')
		cy.get('.modal__content input').first().clear().type('hello world')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('hello world').should('be.visible')

		// check if max length is respected
		cy.get('button').contains('Create row').click()
		cy.get('.modal__content input').first().clear().type('hello world is a typical first phrase to insert')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div').contains('hello world').should('be.visible')
		cy.get('.custom-table table tr td div').contains('phrase').should('not.exist')
	})

})
