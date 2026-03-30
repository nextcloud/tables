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
		cy.get('.app-navigation__list').contains('ToDo list').should('exist').parent().find('button.icon-collapse').click()
		cy.get('.app-navigation__list').contains('Unfinished Tasks').should('exist').click().parent().find('button.action-item__menutoggle').click()
		cy.get('.action-button').contains('Edit view').click()
		const columns = ['Target', 'Description', 'Progress', 'Proofed', 'Comments', 'Task']
		for (let i = 0; i < columns.length; i++) {
			cy.get('[data-cy="selectedViewColumnEl"]').eq(i).should('contain', columns[i]).find('input.checkbox-radio-switch__input').should('be.checked')
		}
		cy.get('#settings-section_filter .v-select').eq(0).should('contain', 'Progress')
		cy.get('#settings-section_filter .v-select').eq(1).should('contain', 'Is lower than')
		cy.get('#settings-section_filter .v-select').eq(2).should('contain', '100')
		cy.get('#settings-section_sort .v-select').should('contain', 'Created at')
		cy.get('#settings-section_sort .checkbox-radio-switch__input[value="DESC"]').should('be.checked')
	})

	it('Export scheme to json', () => {
		const columnFieldsToIgnore = ['id', 'tableId', 'createdAt', 'lastEditAt', 'createdBy', 'createdByDisplayName', 'lastEditBy', 'lastEditByDisplayName']
		cy.get('.app-navigation-toggle-wrapper').should('be.visible').click()
		cy.get('.app-navigation__list').contains('ToDo list').click()
		cy.get('.row.first-row').should('be.visible')
		cy.get('.app-navigation__list').contains('ToDo list').trigger('mouseover')
		cy.get('.app-navigation-entry__actions').eq(2).trigger('mouseover').click()
		cy.get('.action-button__text').contains('Export').click()
		const downloadsFolder = Cypress.config('downloadsFolder')
		cy.readFile('./cypress/e2e/ToDo list.json').then(content => {
			cy.readFile(`${downloadsFolder}/ToDo list.json`).then(expectedContent => {
				expectedContent.columns = omitSubFields(expectedContent.columns, columnFieldsToIgnore)
				content.columns = omitSubFields(content.columns, columnFieldsToIgnore)
				expectedContent.views = omitSubFields(expectedContent.columns, columnFieldsToIgnore)
				content.views = omitSubFields(content.columns, columnFieldsToIgnore)
				content.tablesVersion = ''
				expectedContent.tablesVersion = ''
				expect(JSON.stringify(expectedContent)).to.eq(JSON.stringify(content))
			})
		})
	})
})
