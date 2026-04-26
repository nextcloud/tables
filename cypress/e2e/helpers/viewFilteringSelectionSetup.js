/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const createViewFilteringTable = () => {
	cy.createTable('View filtering test table')
	cy.createTextLineColumn('title', null, null, true)
	cy.createSelectionColumn('selection', ['sel1', 'sel2', 'sel3', 'sel4'], null, false)
	cy.createSelectionMultiColumn('multi selection', ['A', 'B', 'C', 'D'], null, false)
	cy.createSelectionCheckColumn('check', null, false)

	addRow('first row', 'sel1', ['A', 'B'], true)
	addRow('second row', 'sel2', ['B'], true)
	addRow('third row', 'sel3', ['C', 'B', 'D'], false)
	addRow('fourth row', null, ['A'], true)
	addRow('fifths row', 'sel4', ['D'], true)
	addRow('sixths row', 'sel1', ['C', 'D'], true)
	addRow('sevenths row', 'sel2', ['A', 'C', 'B', 'D'], false)

	cy.loadTable('View filtering test table')
}

export const createCheckedFilterView = (title = 'Filter for check enabled') => {
	cy.get('[data-cy="customTableAction"] button').click()
	cy.get('.v-popper__popper li button span').contains('Create view').click({ force: true })
	cy.get('.modal-container #settings-section_title input').type(title)

	cy.get('[data-cy="filterFormFilterGroupBtn"]').click()
	cy.get('.modal-container .filter-group .v-select.select').eq(0).click()
	cy.get('ul.vs__dropdown-menu li span[title="check"]').click()
	cy.get('.modal-container .filter-group .v-select.select').eq(1).click()
	cy.get('ul.vs__dropdown-menu li span[title="Is equal"]').click()
	cy.get('.modal-container .filter-group .v-select.select').eq(2).click()
	cy.get('ul.vs__dropdown-menu li span[title="Checked"]').click()

	cy.intercept({ method: 'POST', url: '**/apps/tables/view' }).as('createView')
	cy.intercept({ method: 'PUT', url: '**/apps/tables/view/*' }).as('updateView')
	cy.contains('button', 'Create View').click()
	cy.wait('@createView')
	cy.wait('@updateView')
	cy.contains('.app-navigation-entry-link span', title).should('exist')
}

export const waitForRowPresenceCheck = (alias, rowId, expectedPresent, retries = 20) => {
	cy.wait(alias, { timeout: 20000 }).then(({ request, response }) => {
		if (request.url.includes(`/row/${rowId}/present`)) {
			if (response.body.present === expectedPresent) {
				return
			}

			if (retries <= 0) {
				expect(response.body.present).to.equal(expectedPresent)
			}

			waitForRowPresenceCheck(alias, rowId, expectedPresent, retries - 1)
			return
		}

		if (retries <= 0) {
			throw new Error(`Did not receive /present check for row ${rowId}`)
		}

		waitForRowPresenceCheck(alias, rowId, expectedPresent, retries - 1)
	})
}

const addRow = (title, selection, multiSelection, checked) => {
	cy.get('[data-cy="createRowBtn"]').click()
	cy.fillInValueTextLine('title', title)

	if (selection !== null) {
		cy.fillInValueSelection('selection', selection)
	}

	cy.fillInValueSelectionMulti('multi selection', multiSelection)

	if (checked) {
		cy.fillInValueSelectionCheck('check')
	}

	cy.get('[data-cy="createRowSaveButton"]').click()
	cy.get('[data-cy="createRowModal"]').should('not.exist')
}
