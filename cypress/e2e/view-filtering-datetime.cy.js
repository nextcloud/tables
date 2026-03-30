/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
const tableTitle = 'View datetime filtering test table'

const today = new Date()
const [tomorrow, yesterday, daysAhead30, daysAhead60, daysAgo30, daysAgo60] = [1, -1, 30, 60, -30, -60].map(days => {
	const d = new Date()
	d.setUTCDate(d.getUTCDate() + days)
	return d
})

const formatDate = (date) => date.toISOString().split('T')[0]

describe('Filtering in a view by datetime', () => {

	before(function () {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function () {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Setup table', () => {
		cy.createTable(tableTitle)
		cy.createTextLineColumn('title', null, null, true)
		cy.createDatetimeDateColumn('date', false, false)

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'today')
		cy.get('.modal__content input.native-datetime-picker--input').clear().type(formatDate(today))
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'tomorrow')
		cy.get('.modal__content input.native-datetime-picker--input').type(formatDate(tomorrow))
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'yesterday')
		cy.get('.modal__content input.native-datetime-picker--input').type(formatDate(yesterday))
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', '30 days ahead')
		cy.get('.modal__content input.native-datetime-picker--input').type(formatDate(daysAhead30))
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', '60 days ahead')
		cy.get('.modal__content input.native-datetime-picker--input').type(formatDate(daysAhead60))
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', '30 days ago')
		cy.get('.modal__content input.native-datetime-picker--input').type(formatDate(daysAgo30))
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', '60 days ago')
		cy.get('.modal__content input.native-datetime-picker--input').type(formatDate(daysAgo60))
		cy.get('[data-cy="createRowSaveButton"]').click()
	})

	it('Filter view for dates 1-30 days ahead', () => {
		cy.loadTable(tableTitle)

		// create view
		const title = 'Next 30 days'
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('[data-cy="dataTableCreateViewBtn"]').contains('Create view').click({ force: true })
		cy.get('[data-cy="viewSettingsDialogSection"] input').type(title)

		// add filter for >= 1 day ahead
		cy.get('[data-cy="filterFormFilterGroupBtn"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(0).click()
		cy.get('ul.vs__dropdown-menu li span[title="date"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(1).click()
		cy.get('ul.vs__dropdown-menu li span[title="Is greater than or equal"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
		cy.get('ul.vs__dropdown-menu li span[title="Number of days ahead"]').click()
		cy.get('[data-cy="filterEntryNumber"]').eq(0).type('1')

		// add filter for <= 30 days ahead
		cy.get('[data-cy="filterGroupAddFilterBtn"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(3).click()
		cy.get('ul.vs__dropdown-menu li span[title="date"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(4).click()
		cy.get('ul.vs__dropdown-menu li span[title="Is lower than or equal"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(5).click()
		cy.get('ul.vs__dropdown-menu li span[title="Number of days ahead"]').click()
		cy.get('[data-cy="filterEntryNumber"]').eq(1).type('30')

		// save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.contains('button', 'Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.contains('.app-navigation-entry-link span', title).should('exist')

		// check for existing rows
		cy.get('.custom-table table tr td div').contains('tomorrow').should('be.visible')
		cy.get('.custom-table table tr td div').contains('30 days ahead').should('be.visible')

		// check for not existing rows
		cy.get('.custom-table table tr td div').contains('today').should('not.exist')
		cy.get('.custom-table table tr td div').contains('yesterday').should('not.exist')
		cy.get('.custom-table table tr td div').contains('60 days ahead').should('not.exist')
		cy.get('.custom-table table tr td div').contains('30 days ago').should('not.exist')
		cy.get('.custom-table table tr td div').contains('60 days ago').should('not.exist')
	})

	it('Filter view for dates 1-30 days ago', () => {
		cy.loadTable(tableTitle)

		// create view
		const title = 'Last 30 days'
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('[data-cy="dataTableCreateViewBtn"]').contains('Create view').click({ force: true })
		cy.get('[data-cy="viewSettingsDialogSection"] input').type(title)

		// add filter for <= 1 day ago
		cy.get('[data-cy="filterFormFilterGroupBtn"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(0).click()
		cy.get('ul.vs__dropdown-menu li span[title="date"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(1).click()
		cy.get('ul.vs__dropdown-menu li span[title="Is lower than or equal"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
		cy.get('ul.vs__dropdown-menu li span[title="Number of days ago"]').click()
		cy.get('[data-cy="filterEntryNumber"]').eq(0).type('1')

		// add filter for >= 30 days ago
		cy.get('[data-cy="filterGroupAddFilterBtn"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(3).click()
		cy.get('ul.vs__dropdown-menu li span[title="date"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(4).click()
		cy.get('ul.vs__dropdown-menu li span[title="Is greater than or equal"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(5).click()
		cy.get('ul.vs__dropdown-menu li span[title="Number of days ago"]').click()
		cy.get('[data-cy="filterEntryNumber"]').eq(1).type('30')

		// save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.contains('button', 'Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.contains('.app-navigation-entry-link span', title).should('exist')

		// check for existing rows
		cy.get('.custom-table table tr td div').contains('yesterday').should('be.visible')
		cy.get('.custom-table table tr td div').contains('30 days ago').should('be.visible')

		// check for not existing rows
		cy.get('.custom-table table tr td div').contains('today').should('not.exist')
		cy.get('.custom-table table tr td div').contains('tomorrow').should('not.exist')
		cy.get('.custom-table table tr td div').contains('30 days ahead').should('not.exist')
		cy.get('.custom-table table tr td div').contains('60 days ahead').should('not.exist')
		cy.get('.custom-table table tr td div').contains('60 days ago').should('not.exist')
	})
})