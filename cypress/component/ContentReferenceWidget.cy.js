/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ContentReferenceWidget from '../../src/views/ContentReferenceWidget.vue'

describe('ContentReferenceWidget', () => {
	let richObject = {}

	before(() => {
		// Load the richObject from a fixture
		cy.fixture('widgets/richObject.json')
			.then(richObjectFixture => {
				richObject = richObjectFixture
			})
	})

	it('mounts', () => {
		mountContentWidget(richObject)

		const title = `${richObject.emoji} ${richObject.title}`

		// Verify the table loaded the richObject
		// by checking the title
		cy.get('[data-cy="contentReferenceWidget"] h2').as('heading')
		cy.get('@heading').contains(title)
	})

	it('can search rows', () => {
		mountContentWidget(richObject)

		const searchTerm = 'cat'

		// Search for the row including the above search term
		cy.get('@options').find('input').first().type(searchTerm)

		// Ensure there is only one resultant row and
		// verify the row correctly includes the search term
		cy.get('@rows').its('length').should('equal', 1)
		cy.get('@rows').first().as('firstRow')
		cy.get('@firstRow').children().first().contains(searchTerm, { matchCase: false })
	})

	it('can create a row', () => {
		mountContentWidget(richObject);

		// Load a fixture used to reply to the create row request
		cy.fixture('widgets/createRow.json')
			.then((rowData) => {
				cy.reply('**/ocs/v2.php/apps/tables/api/2/tables/*/rows', rowData)
				
				// Also mock the reload rows endpoint to return updated rows including the new one
				const updatedRows = [...richObject.rows, rowData]
				cy.reply('**/apps/tables/row/table/*', updatedRows)
			})

		// Click the Create Row button
		cy.get('@options').find('button').first().click()

		// Input row data
		cy.get('[data-cy="Name"] input').type('Hello')
		cy.get('[data-cy="Account manager"] input').type('World')

		// Create the row and make sure the modal disappears
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('.modal__content').should('not.exist')

		// Make sure the row was added and is visible
		cy.get('@rows').last().children().as('createdRow')
		cy.get('@createdRow').first().contains('Hello')
		cy.get('@createdRow').next().contains('World')
	})

	it('can edit a row', () => {
		mountContentWidget(richObject)
		
		// Load a fixture which is used to reply to the edit row request
		cy.fixture('widgets/editRow.json')
			.then((rowData) => {
				cy.reply('**/index.php/apps/tables/row/*', rowData)
			})

		// Click the edit button on the first row
		cy.get('@rows').first().find('td.sticky button').click({ force: true })

		// Get the first field of the Edit Row modal
		cy.get('.modal__content').as('editRowModal')
		cy.get('@editRowModal').find('.row.space-T').as('fields')
		cy.get('@fields').first().find('input').as('editNameField')

		// Clear the current input and enter a new value
		cy.get('@editNameField').clear()
		cy.get('@editNameField').type('Giraffe')

		// Edit the row and make sure the modal disappears
		cy.get('[data-cy="editRowSaveButton"]').click()
		cy.get('@editRowModal').should('not.exist')

		// Check the edited row for the new value
		cy.get('@rows').first().children().as('editedRow')
		cy.get('@editedRow').first().contains('Giraffe')
	})
})

function mountContentWidget(richObject) {
	cy.reply('**/apps/tables/row/table/*', richObject.rows)

	cy.mount(ContentReferenceWidget, {
		propsData: {
			richObject,
		},
	})

	// Get some often used elements
	cy.get('[data-cy="contentReferenceWidget"] .options').as('options')
	cy.get('[data-cy="contentReferenceWidget"] .NcTable table').as('table')
	cy.get('@table').find('tr[data-cy="customTableRow"]').as('rows')
}
