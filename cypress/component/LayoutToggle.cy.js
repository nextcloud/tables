/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import NcTable from '../../src/shared/components/ncTable/NcTable.vue'

const TOGGLE = '[data-cy="layoutToggle"]'
const TILES_OPTION = '[data-cy="layoutToggleTiles"]'
const GALLERY_OPTION = '[data-cy="layoutToggleGallery"]'
const CARD = '[data-cy="tilesLayoutCard"]'
const ROW = 'tr[data-cy="customTableRow"]'

describe('Layout toggle', () => {
	let richObject = {}

	before(() => {
		cy.fixture('widgets/richObject.json')
			.then(richObjectFixture => {
				richObject = richObjectFixture
			})
	})

	it('stays hidden on a view that is not set up for cards', () => {
		mountTable(richObject, { layout: 'table', viewSettings: null })

		cy.get(TOGGLE).should('not.exist')
	})

	it('shows once a card title source is configured, with a descriptive label', () => {
		mountTable(richObject, { layout: 'table', viewSettings: { cardBackgroundSource: null, cardTitleSource: richObject.columns[0].id } })

		cy.get(TOGGLE).should('exist')
		cy.get(`${TOGGLE} button`).first().should('have.attr', 'aria-label', 'Switch layout')
		// The hover text sits on the wrapper, which is what NcActions passes it to.
		cy.get(TOGGLE).should('have.attr', 'title', 'Switch layout')
	})

	it('shows on a view already saved with a card layout', () => {
		mountTable(richObject, { layout: 'gallery', viewSettings: null })

		cy.get(TOGGLE).should('exist')
	})

	it('leaves no adjustment when the saved layout is picked again', () => {
		mountTable(richObject, { layout: 'gallery', viewSettings: null })
			.then(({ wrapper }) => {
			cy.get(TOGGLE).find('button').first().click()
			cy.get(GALLERY_OPTION).click()
			.then(() => {
				const emitted = wrapper.emitted('update:viewSetting')
				// Nothing to reset: the choice matches what the view already is.
				expect(emitted[emitted.length - 1][0]).to.not.have.property('layout')
			})
		})
	})

	it('stays hidden where the embedder declines it', () => {
		mountTable(richObject, { layout: 'gallery', viewSettings: null }, { canSwitchLayout: false })

		cy.get('[data-cy="galleryLayoutCard"]').should('exist')
		cy.get(TOGGLE).should('not.exist')
	})

	it('switches the rendering without touching the saved layout', () => {
		mountTable(richObject, { layout: 'table', viewSettings: { cardBackgroundSource: null, cardTitleSource: richObject.columns[0].id } })
			.then(({ wrapper }) => {
			cy.get(ROW).should('exist')
			cy.get(TOGGLE).find('button').first().click()
			cy.get(TILES_OPTION).click()
			// The menu closes on selection rather than staying over the cards.
			cy.get(TILES_OPTION).should('not.exist')

			cy.get(CARD).should('exist')
			cy.get(ROW).should('not.exist')
			.then(() => {
				// The view keeps the layout it was given; only the local setting moved.
				expect(wrapper.props('layout')).to.equal('table')
			})
		})
	})

	it('falls back to the saved layout when the local choice is cleared', () => {
		mountTable(richObject, { layout: 'gallery', viewSettings: null })
			.then(({ wrapper }) => {
			cy.get('[data-cy="galleryLayoutCard"]').should('exist')
			cy.get(TOGGLE).should('exist')

			cy.wrap(null).then(() => wrapper.setProps({ viewSetting: { layout: 'table' } }))
			cy.get(ROW).should('exist')

			cy.wrap(null).then(() => wrapper.setProps({ viewSetting: {} }))
			cy.get('[data-cy="galleryLayoutCard"]').should('exist')
		})
	})
})

/**
 * Mounts NcTable as a view with the given saved layout.
 *
 * @param {object} richObject the widget fixture holding the column and row templates
 * @param {object} view the saved layout and card sources of the view
 * @param {object} overrides further props to mount with
 * @return {Cypress.Chainable} the mount result
 */
function mountTable(richObject, view, overrides = {}) {
	return cy.mount(NcTable, {
		props: {
			rows: richObject.rows,
			columns: richObject.columns,
			elementId: richObject.id,
			isView: true,
			layout: view.layout,
			viewSettings: view.viewSettings,
			viewSetting: {},
			...overrides,
		},
	})
}
