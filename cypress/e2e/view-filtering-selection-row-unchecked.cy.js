/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createCheckedFilterView, createViewFilteringTable, waitForRowPresenceCheck } from './helpers/viewFilteringSelectionSetup'

let localUser

describe('Filtering view with unchecked row insertion', () => {
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

	it('Hides inserted unchecked row from the filtered view', () => {
		cy.intercept({ method: 'POST', url: '**/apps/tables/api/2/views/*/rows' }).as('insertUncheckedRowInView')
		cy.intercept({ method: 'GET', url: '**/apps/tables/view/*/row/*/present' }).as('isRowInViewPresentUnchecked')
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'unchecked row')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.wait('@insertUncheckedRowInView').then(({ response }) => {
			const uncheckedRowId = response.body.ocs.data.id
			waitForRowPresenceCheck('@isRowInViewPresentUnchecked', uncheckedRowId, false)
		})

		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'unchecked row').should('not.exist')
		cy.get('[data-cy="createRowModal"]').should('not.exist')
	})
})
