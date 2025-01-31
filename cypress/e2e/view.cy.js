/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
const title = 'Test view'

describe('Interact with views', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')

		cy.createTable('View test table')
		cy.createTextLineColumn('title', null, null, true)
		cy.createSelectionColumn('selection', ['sel1', 'sel2', 'sel3', 'sel4'], null, false)

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'first row')
		cy.fillInValueSelection('selection', 'sel1')
		cy.get('button').contains('Save').click()

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'second row')
		cy.fillInValueSelection('selection', 'sel2')
		cy.get('button').contains('Save').click()

		// add row
		cy.get('button').contains('Create row').click()
		cy.fillInValueTextLine('title', 'sevenths row')
		cy.fillInValueSelection('selection', 'sel2')
		cy.get('button').contains('Save').click()

		// create view
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('[data-cy="dataTableCreateViewBtn"]').contains('Create view').click({ force: true })
		cy.get('[data-cy="viewSettingsDialogSection"] input').type(title)
	})

	// cleanup
	afterEach(function() {
		// delete table (with view)
		cy.get('[data-cy="navigationTableItem"]').contains('View test table').click({ force: true })
		cy.get('[data-cy="customTableAction"] button').click()
		cy.get('[data-cy="dataTableEditTableBtn"]').contains('Edit table').click()
		cy.get('[data-cy="editTableModal"]').should('be.visible')
		cy.get('[data-cy="editTableModal"] button').contains('Delete').click()
		cy.get('[data-cy="editTableModal"] button').contains('I really want to delete this table!').click()
		cy.wait(10).get('.toastify.toast-success').should('be.visible')
		cy.get('[data-cy="navigationTableItem"]').contains('View test table').should('not.exist')
		cy.get('[data-cy="navigationTableItem"]').contains(title).should('not.exist')
	})

	it('Create view and insert rows in the view', () => {
		// ## add filter
		cy.get('[data-cy="filterFormFilterGroupBtn"]').contains('Add new filter group').click()
		cy.get('[data-cy="filterEntryColumn"]').click()
		cy.get('ul.vs__dropdown-menu li span[title="selection"]').click()
		cy.get('[data-cy="filterEntryOperator"]').click()
		cy.get('ul.vs__dropdown-menu li span[title="Is equal"]').click()
		cy.get('[data-cy="filterEntrySeachValue"]').click()
		cy.get('ul.vs__dropdown-menu li span[title="sel2"]').click()

		// ## save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.get('[data-cy="modifyViewBtn"]').contains('Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.get('[data-cy="navigationViewItem"]').contains(title).should('exist')

		const expected = ['sevenths row', 'second row']
		expected.forEach(item => {
			cy.get('[data-cy="customTableRow"] td div').contains(item).should('be.visible')
		})

		// Add new row in the view
		cy.get('[data-cy="createRowBtn"]').contains('Create row').click()
		cy.fillInValueTextLine('title', 'new row')
		cy.fillInValueSelection('selection', 'sel2')
		cy.get('[data-cy="createRowSaveButton"]').contains('Save').click()

		expected.push('new row')
		expected.forEach(item => {
			cy.get('[data-cy="customTableRow"] td div').contains(item).should('be.visible')
		})
	})

	it('Create view and update rows in the view', () => {
		// ## save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.get('[data-cy="modifyViewBtn"]').contains('Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.get('[data-cy="navigationViewItem"]').contains(title).should('exist')

		// Update rows in the view
		cy.get('[data-cy="customTableRow"]').contains('first row').parent().parent().find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowModal"] input').first().clear().type('Changed row')
		cy.get('[data-cy="editRowSaveButton"]').contains('Save').click()

		cy.get('[data-cy="editRowModal"]').should('not.exist')
		cy.get('[data-cy="customTableRow"]').contains('first row').should('not.exist')
		cy.get('[data-cy="customTableRow"]').contains('Changed row').should('exist')
	})

	it('Create view and delete rows in the view', () => {

		// ## save view
		cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
		cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
		cy.get('[data-cy="modifyViewBtn"]').contains('Create View').click()
		cy.wait('@createView')
		cy.wait('@updateView')
		cy.get('[data-cy="navigationViewItem"]').contains(title).should('exist')

		// Delete rows in the view
		cy.get('[data-cy="customTableRow"]').contains('first row').parent().parent().find('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowModal"] [data-cy="editRowDeleteButton"]').contains('Delete').click()
		cy.get('[data-cy="editRowModal"] [data-cy="editRowDeleteConfirmButton"]').contains('I really want to delete this row!').click()

		cy.get('[data-cy="editRowModal"]').should('not.exist')
		cy.get('[data-cy="customTableRow"]').contains('first row').should('not.exist')
	})
})