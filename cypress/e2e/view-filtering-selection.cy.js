/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Filtering in a view by selection columns', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Setup table', () => {
		cy.createTable('View filtering test table')
		cy.createTextLineColumn('title', null, null, true)
		cy.createSelectionColumn('selection', ['sel1', 'sel2', 'sel3', 'sel4'], null, false)
		cy.createSelectionMultiColumn('multi selection', ['A', 'B', 'C', 'D'], null, false)
		cy.createSelectionCheckColumn('check', null, false)

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'first row')
		cy.fillInValueSelection('selection', 'sel1')
		cy.fillInValueSelectionMulti('multi selection', ['A', 'B'])
		cy.fillInValueSelectionCheck('check')
		cy.get('button').contains('Save').click()

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'second row')
		cy.fillInValueSelection('selection', 'sel2')
		cy.fillInValueSelectionMulti('multi selection', ['B'])
		cy.fillInValueSelectionCheck('check')
		cy.get('button').contains('Save').click()

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'third row')
		cy.fillInValueSelection('selection', 'sel3')
		cy.fillInValueSelectionMulti('multi selection', ['C', 'B', 'D'])
		cy.get('button').contains('Save').click()

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'fourth row')
		cy.fillInValueSelectionMulti('multi selection', ['A'])
		cy.fillInValueSelectionCheck('check')
		cy.get('button').contains('Save').click()

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'fifths row')
		cy.fillInValueSelection('selection', 'sel4')
		cy.fillInValueSelectionMulti('multi selection', ['D'])
		cy.fillInValueSelectionCheck('check')
		cy.get('button').contains('Save').click()

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'sixths row')
		cy.fillInValueSelection('selection', 'sel1')
		cy.fillInValueSelectionMulti('multi selection', ['C', 'D'])
		cy.fillInValueSelectionCheck('check')
		cy.get('button').contains('Save').click()

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'sevenths row')
		cy.fillInValueSelection('selection', 'sel2')
		cy.fillInValueSelectionMulti('multi selection', ['A', 'C', 'B', 'D'])
		cy.get('button').contains('Save').click()
	})

	it('Filter view for single selection', () => {
		cy.loadTable('View filtering test table')

		// # create view with filter
		// ## create view and set title
		const title = 'Filter for single selection'
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Create view').click({ force: true })
		cy.get('.modal-container #settings-section_title input').type(title)

		// ## add filter
		cy.get('button').contains('Add new filter group').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(0).click()
		cy.get('ul.vs__dropdown-menu li span[title="selection"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(1).click()
		cy.get('ul.vs__dropdown-menu li span[title="Is equal"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
		cy.get('ul.vs__dropdown-menu li span[title="sel2"]').click()

		// ## save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.contains('button', 'Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.contains('.app-navigation-entry-link span', title).should('exist')

		// # check for expected rows
		// ## expected
		let expected = ['sevenths row', 'second row']
		expected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('be.visible')
		})

		// ## not expected
		let unexpected = ['first row', 'third row', 'fourth row', 'fifths row', 'sixths row']
		unexpected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('not.exist')
		})

		// # change filter value
		// ## adjust filter
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Edit view').click({ force: true })
		cy.get('[data-cy="viewSettingsDialog"]').should('be.visible')
		cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
		cy.get('ul.vs__dropdown-menu li span').first().click()

		// ## update view
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.contains('button', 'Save View').click()
		cy.wait('@updateView')

		// # check for expected rows
		// ## expected
		expected = ['first row', 'sixths row']
		expected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('be.visible')
		})

		// ## not expected
		unexpected = ['second row', 'third row', 'fourth row', 'fifths row', 'sevenths row']
		unexpected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('not.exist')
		})
	})

	it('Filter view for multi selection - equals', () => {
		cy.loadTable('View filtering test table')

		// # create view with filter
		// ## create view and set title
		const title = 'Filter for multi selection 1'
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Create view').click({ force: true })
		cy.get('.modal-container #settings-section_title input').type(title)

		// ## add filter
		cy.get('button').contains('Add new filter group').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(0).click()
		cy.get('ul.vs__dropdown-menu li span[title="multi selection"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(1).click()
		cy.get('ul.vs__dropdown-menu li span[title="Is equal"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
		cy.get('ul.vs__dropdown-menu li span[title="A"]').click()

		// ## save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.contains('button', 'Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.contains('.app-navigation-entry-link span', title).should('exist')

		// # check for expected rows
		// ## expected
		const expected = ['fourth row']
		expected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('be.visible')
		})

		// ## not expected
		const unexpected = ['first row', 'second row', 'third row', 'fifths row', 'sixths row', 'sevenths row']
		unexpected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('not.exist')
		})
	})

	it('Filter view for multi selection - contains', () => {
		cy.loadTable('View filtering test table')

		// # create view with filter
		// ## create view and set title
		const title = 'Filter for multi selection 2'
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Create view').click({ force: true })
		cy.get('.modal-container #settings-section_title input').type(title)

		// ## add filter
		cy.get('button').contains('Add new filter group').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(0).click()
		cy.get('ul.vs__dropdown-menu li span[title="multi selection"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(1).click()
		cy.get('ul.vs__dropdown-menu li span[title="Contains"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
		cy.get('ul.vs__dropdown-menu li span[title="A"]').click()

		// ## save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.contains('button', 'Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.contains('.app-navigation-entry-link span', title).should('exist')

		// # check for expected rows
		// ## expected
		const expected = ['first row', 'fourth row', 'sevenths row']
		expected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('be.visible')
		})

		// ## not expected
		const unexpected = ['second row', 'third row', 'fifths row', 'sixths row']
		unexpected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('not.exist')
		})
	})

	it('Filter view for multi selection - multiple contains', () => {
		cy.loadTable('View filtering test table')

		// # create view with filter
		// ## create view and set title
		const title = 'Filter for multi selection 3'
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Create view').click({ force: true })
		cy.get('.modal-container #settings-section_title input').type(title)

		// ## add filter
		cy.get('button').contains('Add new filter group').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(0).click()
		cy.get('ul.vs__dropdown-menu li span[title="multi selection"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(1).click()
		cy.get('ul.vs__dropdown-menu li span[title="Contains"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
		cy.get('ul.vs__dropdown-menu li span[title="A"]').click()

		cy.get('button').contains('Add new filter').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(3).click()
		cy.get('ul.vs__dropdown-menu li span[title="multi selection"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(4).click()
		cy.get('ul.vs__dropdown-menu li span[title="Contains"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(5).click()
		cy.get('ul.vs__dropdown-menu li span[title="B"]').click()

		// ## save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.contains('button', 'Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.contains('.app-navigation-entry-link span', title).should('exist')

		// # check for expected rows
		// ## expected
		const expected = ['first row', 'sevenths row']
		expected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('be.visible')
		})

		// ## not expected
		const unexpected = ['second row', 'third row', 'fourth row', 'fifths row', 'sixths row']
		unexpected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('not.exist')
		})
	})

	it('Filter view for multi selection - multiple filter groups', () => {
		cy.loadTable('View filtering test table')

		// # create view with filter
		// ## create view and set title
		const title = 'Filter for multi selection 3'
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Create view').click({ force: true })
		cy.get('.modal-container #settings-section_title input').type(title)

		// ## add filter
		cy.get('button').contains('Add new filter group').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(0).click()
		cy.get('ul.vs__dropdown-menu li span[title="multi selection"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(1).click()
		cy.get('ul.vs__dropdown-menu li span[title="Contains"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
		cy.get('ul.vs__dropdown-menu li span[title="A"]').click()

		cy.get('button').contains('Add new filter').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(3).click()
		cy.get('ul.vs__dropdown-menu li span[title="multi selection"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(4).click()
		cy.get('ul.vs__dropdown-menu li span[title="Contains"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(5).click()
		cy.get('ul.vs__dropdown-menu li span[title="B"]').click()

		cy.get('button').contains('Add new filter group').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(6).click()
		cy.get('ul.vs__dropdown-menu li span[title="multi selection"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(7).click()
		cy.get('ul.vs__dropdown-menu li span[title="Contains"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(8).click()
		cy.get('ul.vs__dropdown-menu li span[title="D"]').click()

		// ## save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.contains('button', 'Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.contains('.app-navigation-entry-link span', title).should('exist')

		// # check for expected rows
		// ## expected
		const expected = ['first row', 'third row', 'fifths row', 'sixths row', 'sevenths row']
		expected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('be.visible')
		})

		// ## not expected
		const unexpected = ['second row', 'fourths row']
		unexpected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('not.exist')
		})
	})

	it('Filter view for selection check', () => {
		cy.loadTable('View filtering test table')

		// # create view with filter
		// ## create view and set title
		const title = 'Filter for check selection'
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Create view').click({ force: true })
		cy.get('.modal-container #settings-section_title input').type(title)

		// ## add filter
		cy.get('button').contains('Add new filter group').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(0).click()
		cy.get('ul.vs__dropdown-menu li span[title="check"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(1).click()
		cy.get('ul.vs__dropdown-menu li span[title="Is equal"]').click()
		cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
		cy.get('ul.vs__dropdown-menu li span[title="Checked"]').click()

		// ## save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.contains('button', 'Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.contains('.app-navigation-entry-link span', title).should('exist')

		// # check for expected rows
		// ## expected
		const expected = ['first row', 'second row', 'fourth row', 'fifths row', 'sixths row']
		expected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('be.visible')
		})

		// ## not expected
		const unexpected = ['third row', 'sevenths row']
		unexpected.forEach(item => {
			cy.get('.custom-table table tr td div').contains(item).should('not.exist')
		})
	})
})
