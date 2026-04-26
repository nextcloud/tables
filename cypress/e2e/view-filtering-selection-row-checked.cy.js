/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createCheckedFilterView, createViewFilteringTable, waitForRowPresenceCheck } from './helpers/viewFilteringSelectionSetup'

let localUser

describe('Filtering view with checked row insertion', () => {
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

	it('Shows inserted checked row in the filtered view', () => {
		cy.intercept({ method: 'POST', url: '**/apps/tables/api/2/views/*/rows' }).as('insertRowInView')
		cy.intercept({ method: 'GET', url: '**/apps/tables/view/*/row/*/present' }).as('isRowInViewPresentChecked')
		cy.get('[data-cy="createRowBtn"]').click()
		cy.fillInValueTextLine('title', 'checked row')
		cy.fillInValueSelectionCheck('check')
		cy.get('[data-cy="createRowSaveButton"]').click()

		cy.wait('@insertRowInView').then(({ response }) => {
			const checkedRowId = response.body.ocs.data.id
			waitForRowPresenceCheck('@isRowInViewPresentChecked', checkedRowId, true)
		})

		cy.contains('[data-cy="ncTable"] [data-cy="customTableRow"]', 'checked row').should('be.visible')
		cy.get('[data-cy="createRowModal"]').should('not.exist')
	})
})
