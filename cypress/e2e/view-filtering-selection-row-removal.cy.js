/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Filtering in a view by selection columns (Cypress supplement – row removal)', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
		cy.createTable('View filtering test table')
		cy.createTextLineColumn('title', null, null, true)
		cy.createSelectionColumn('selection', ['sel1', 'sel2', 'sel3', 'sel4'], null, false)
		cy.createSelectionMultiColumn('multi selection', ['A', 'B', 'C', 'D'], null, false)
		cy.createSelectionCheckColumn('check', null, false)

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'first row')
		cy.fillInValueSelection('selection', 'sel1')
		cy.fillInValueSelectionMulti('multi selection', ['A', 'B'])
		cy.fillInValueSelectionCheck('check')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'second row')
		cy.fillInValueSelection('selection', 'sel2')
		cy.fillInValueSelectionMulti('multi selection', ['B'])
		cy.fillInValueSelectionCheck('check')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'third row')
		cy.fillInValueSelection('selection', 'sel3')
		cy.fillInValueSelectionMulti('multi selection', ['C', 'B', 'D'])
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'fourth row')
		cy.fillInValueSelectionMulti('multi selection', ['A'])
		cy.fillInValueSelectionCheck('check')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'fifths row')
		cy.fillInValueSelection('selection', 'sel4')
		cy.fillInValueSelectionMulti('multi selection', ['D'])
		cy.fillInValueSelectionCheck('check')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'sixths row')
		cy.fillInValueSelection('selection', 'sel1')
		cy.fillInValueSelectionMulti('multi selection', ['C', 'D'])
		cy.fillInValueSelectionCheck('check')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// add row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'sevenths row')
		cy.fillInValueSelection('selection', 'sel2')
		cy.fillInValueSelectionMulti('multi selection', ['A', 'C', 'B', 'D'])
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.loadTable('View filtering test table')
	})

	it('Filter view remove row when it no longer matches filter', () => {
		// # create view with filter
		// ## create view and set title
		const title = 'Filter for check enabled'
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('.v-popper__popper li button span').contains('Create view').click({ force: true })
		cy.get('.modal-container #settings-section_title input').type(title)

		// ## add filter
		cy.get('[data-cy="filterFormFilterGroupBtn"]').click()
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

		// # insert a checked row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'checked row')
		cy.fillInValueSelectionCheck('check')
		cy.intercept({ method: 'GET', url: '**/apps/tables/view/*/row/*/present' }).as('isRowInViewPresent')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// ## check server response for /view/{viewId}/row/{id}/present
		cy.wait('@isRowInViewPresent').then(({ response: { body: { present } } }) => {
			expect(present).to.equal(true)
		})

		// ## check if row is visible
		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'checked row').should('be.visible')

		// # insert a unchecked row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'unchecked row')
		cy.intercept({ method: 'GET', url: '**/apps/tables/view/*/row/*/present' }).as('isRowInViewPresent')
		cy.get('[data-cy="createRowSaveButton"]').click()

		// ## check server response for /view/{viewId}/row/{id}/present
		cy.wait('@isRowInViewPresent').then(({ response: { body: { present } } }) => {
			expect(present).to.equal(false)
		})

		// ## check if row does not exist
		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'unchecked row').should('not.exist')

		// # edit checked row
		// ## uncheck
		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'checked row').closest('[data-cy="customTableRow"]').find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowModal"] .checkbox-radio-switch').click()
		cy.intercept({ method: 'GET', url: '**/apps/tables/view/*/row/*/present' }).as('isRowInViewPresent')
		cy.get('[data-cy="editRowSaveButton"]').click()

		// ## check server response for /view/{viewId}/row/{id}/present
		cy.wait('@isRowInViewPresent').then(({ response: { body: { present } } }) => {
			expect(present).to.equal(false)
		})

		// ## check if row does not exist
		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'checked row').should('not.exist')

		// # inline edit row
		// ## uncheck row
		cy.intercept({ method: 'GET', url: '**/apps/tables/view/*/row/*/present' }).as('isRowInViewPresent')
		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'first row').closest('[data-cy="customTableRow"]').find('.inline-editing-container input').click({ force: true })

		// ## check server response for /view/{viewId}/row/{id}/present
		cy.wait('@isRowInViewPresent').then(({ response: { body: { present } } }) => {
			cy.wait(1000)
			expect(present).to.equal(false)
		})

		// ## check if row does not exist
		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'first row').should('not.exist')
	})
})
