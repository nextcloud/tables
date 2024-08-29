/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

const omitSubFields = (array, fields) => {
	return array.map(
		(item) => Object.keys(item).filter(
			filterKey => !fields.includes(filterKey)).reduce(
				(obj, key) => {
					obj[key] = item[key]
					return obj
				}, {}),
	)
}

describe('Import Export Scheme', () => {

	before(function () {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function () {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Import table from scheme', () => {
		cy.get('.icon-loading').should('not.exist')
		cy.get('[data-cy="navigationCreateTableIcon"]').click({ force: true })
		cy.get('p').contains('Import Scheme').click({ force: true })
		cy.contains('button', 'Create table').scrollIntoView().click()
		cy.get('input[type=file].hidden-visually').selectFile('./cypress/e2e/ToDo list.json', { force: true })
		cy.get('button').contains('Import').click()
		cy.get('.app-navigation-toggle-wrapper').should('be.visible').click()
		cy.get('.app-navigation__list').contains('ToDo list').should('exist')
	})

	it('Export scheme to json', () => {
		const columnFieldsToIgnore = ['id', 'tableId', 'createdAt', 'lastEditAt', 'createdBy', 'createdByDisplayName', 'lastEditBy', 'lastEditByDisplayName']
		cy.get('.app-navigation-toggle-wrapper').should('be.visible').click()
		cy.get('.app-navigation__list').contains('ToDo list').click()
		cy.get('.row.first-row').should('be.visible')
		cy.get('.app-navigation__list').contains('ToDo list').trigger('mouseover')
		cy.get('.app-navigation-entry__actions').last('').click()
		cy.get('.action-button__text').contains('Export').click()
		const downloadsFolder = Cypress.config('downloadsFolder')
		cy.readFile('./cypress/e2e/ToDo list.json').then(content => {
			cy.readFile(`${downloadsFolder}/ToDo list.json`).then(expectedContent => {
				expectedContent.columns = omitSubFields(expectedContent.columns, columnFieldsToIgnore)
				content.columns = omitSubFields(content.columns, columnFieldsToIgnore)
				content.tablesVersion = ''
				expectedContent.tablesVersion = ''
				expect(JSON.stringify(expectedContent)).to.eq(JSON.stringify(content))
			})
		})
	})
})
