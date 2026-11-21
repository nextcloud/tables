/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { translate } from '@nextcloud/l10n'
import { createPinia, setActivePinia } from 'pinia'
import { useDataStore } from '../../src/store/data.js'

const VIEW_ID = 6
const TABLE_ID = 4
const ROW_ID = 12

const notFound = {
	statusCode: 404,
	body: { ocs: { meta: { status: 'failure', statuscode: 404 }, data: { message: 'A not found error occurred.' } } },
}

const serverError = {
	statusCode: 500,
	body: { ocs: { meta: { status: 'failure', statuscode: 500 }, data: { message: 'An unexpected error occurred.' } } },
}

describe('Changing a row that is no longer reachable', () => {
	let store

	before(() => {
		// The store uses the translation function the app provides globally.
		window.t = translate
	})

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useDataStore()
		cy.intercept('GET', `**/apps/tables/row/view/${VIEW_ID}`, { body: [] }).as('reloadView')
		cy.intercept('GET', `**/apps/tables/row/table/${TABLE_ID}`, { body: [] }).as('reloadTable')
	})

	it('reloads the view when an update answers 404', () => {
		cy.intercept('PUT', `**/apps/tables/api/2/views/${VIEW_ID}/rows/${ROW_ID}`, notFound)

		cy.wrap(null).then(() => store.updateRow({ id: ROW_ID, isView: true, elementId: VIEW_ID, data: {} }))
			.should('equal', false)
		cy.wait('@reloadView')
	})

	it('reloads the table when a delete answers 404', () => {
		cy.intercept('DELETE', `**/apps/tables/api/2/tables/${TABLE_ID}/rows/${ROW_ID}`, notFound)

		cy.wrap(null).then(() => store.removeRow({ rowId: ROW_ID, isView: false, elementId: TABLE_ID }))
			.should('equal', false)
		cy.wait('@reloadTable')
	})

	it('does not reload on another error', () => {
		cy.intercept('PUT', `**/apps/tables/api/2/views/${VIEW_ID}/rows/${ROW_ID}`, serverError)

		cy.wrap(null).then(() => store.updateRow({ id: ROW_ID, isView: true, elementId: VIEW_ID, data: {} }))
			.should('equal', false)
		cy.get('@reloadView.all').should('have.length', 0)
	})
})
