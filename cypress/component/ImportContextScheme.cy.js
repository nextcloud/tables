/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ImportContextScheme from '../../src/modules/modals/ImportContextScheme.vue'

const REFUSED = 'Node 0 of the scheme is not a table or view that is here and can be managed by the user.'

describe('Import application scheme', () => {
	it('shows the reason the server gives for a refused import', () => {
		importAnswering(400, { ocs: { meta: { status: 'failure' }, data: { message: REFUSED } } })

		cy.contains(REFUSED).should('exist')
	})

	it('says the scheme cannot be used here for a refused import without a reason', () => {
		importAnswering(400, { ocs: { meta: { status: 'failure' }, data: {} } })

		cy.contains('Could not import, the scheme cannot be used here.').should('exist')
	})

	it('does not show a server message for an unknown error', () => {
		importAnswering(500, { ocs: { meta: { status: 'failure' }, data: { message: 'SQLSTATE[23502]' } }, message: 'SQLSTATE[23502]' })

		cy.contains('Could not import scheme due to unknown errors.').should('exist')
		cy.contains('SQLSTATE').should('not.exist')
	})
})

/**
 * Mounts the dialog and imports a scheme the server answers with the given status and body.
 *
 * @param {number} statusCode status of the import response
 * @param {object} body body of the import response
 */
function importAnswering(statusCode, body) {
	cy.intercept('POST', '**/contexts/7/scheme/import', { statusCode, body }).as('import')
	cy.mount(ImportContextScheme, { props: { showModal: true, contextId: 7 } })
		.then(({ wrapper }) => {
			wrapper.vm.schemeData = { name: 'Intake', iconName: 'star', description: '', nodes: [], tables: { addTables: [], modifyTables: [] } }
			wrapper.vm.actionImport()
		})
	cy.wait('@import')
}
