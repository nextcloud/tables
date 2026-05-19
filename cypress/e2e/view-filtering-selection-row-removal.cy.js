/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createCheckedFilterView, createViewFilteringTable } from './helpers/viewFilteringSelectionSetup.js'

let localUser

describe('Filtering view with row removal', () => {
	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
		createViewFilteringTable()
		createCheckedFilterView()
	})

	it('Removes rows from the filtered view once they no longer match', () => {
		cy.intercept({ method: 'PUT', url: '**/apps/tables/row/*' }).as('updateCheckedRow')
		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'first row').closest('[data-cy="customTableRow"]').find('[data-cy="rowActionMenu"] button').click()
		cy.get('[data-cy="editRowBtn"]').click()
		cy.get('[data-cy="editRowModal"] .checkbox-radio-switch').click()
		cy.get('[data-cy="editRowSaveButton"]').click()
		cy.wait('@updateCheckedRow')
		// Wait for the row to be removed from the filtered view (async removal)
		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'first row', { timeout: 8000 }).should('not.exist')
		cy.get('[data-cy="editRowModal"]').should('not.exist')

		cy.intercept({ method: 'PUT', url: '**/apps/tables/row/*' }).as('inlineUpdateRow')
		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'second row').closest('[data-cy="customTableRow"]').find('.inline-editing-container input').click({ force: true })
		cy.wait('@inlineUpdateRow')
		// Wait for the row to be removed from the filtered view (async removal)
		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'second row', { timeout: 8000 }).should('not.exist')
	})
})
