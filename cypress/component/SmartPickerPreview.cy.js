/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import SmartPicker from '../../src/views/SmartPicker.vue'

describe('SmartPicker content preview', () => {
	let fixture = {}

	before(() => {
		cy.fixture('widgets/richObject.json').then(richObject => {
			fixture = richObject
		})
	})

	it('previews a view saved as a gallery in its own layout', () => {
		mountPicker(fixture, { layout: 'gallery', viewSettings: { cardBackgroundSource: null, cardTitleSource: 159 } })

		cy.get('[data-cy="galleryLayoutCard"]').should('have.length', fixture.rows.length)
		cy.get('tr[data-cy="customTableRow"]').should('not.exist')
	})

	it('previews a view saved as tiles in its own layout', () => {
		mountPicker(fixture, { layout: 'tiles', viewSettings: { cardBackgroundSource: null, cardTitleSource: 159 } })

		cy.get('[data-cy="tilesLayoutCard"]').should('have.length', fixture.rows.length)
		cy.get('tr[data-cy="customTableRow"]').should('not.exist')
	})

	it('previews a table as a table', () => {
		mountPicker(fixture, { layout: 'table', viewSettings: null }, 'table')

		cy.get('tr[data-cy="customTableRow"]').should('have.length', fixture.rows.length)
		cy.get('[data-cy$="LayoutCard"]').should('not.exist')
	})
})

/**
 * Mounts the picker with a selection already made and the content render mode active.
 *
 * @param {object} fixture the widget fixture supplying columns and rows
 * @param {object} view the layout payload the view endpoint should return
 * @param {string} type the node type of the selection
 */
function mountPicker(fixture, view, type = 'view') {
	cy.reply('**/apps/tables/api/1/*s/*/columns', fixture.columns)
	cy.reply('**/apps/tables/row/*/*', fixture.rows)
	cy.reply('**/apps/tables/api/1/views/*', view)

	cy.mount(SmartPicker).then(({ wrapper }) => {
		wrapper.vm.renderMode = 'content'
		wrapper.vm.value = {
			value: 6,
			type,
			label: fixture.title,
			emoji: fixture.emoji,
			owner: fixture.ownership,
			ownerDisplayName: fixture.ownerDisplayName,
			rowsCount: fixture.rows.length,
		}
	})
}
