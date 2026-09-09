/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import NcTable from '../../src/shared/components/ncTable/NcTable.vue'
import { h } from 'vue'

const ROWS_PER_PAGE = 100
const ROW_COUNT = 150

const CARD_LAYOUT = { layout: 'tiles' }

const TOOLBAR_PAGINATION = '.options .pagination-block'
const FOOTER_PAGINATION = '.pagination-footer'
const PAGE_INPUT = 'input[type="number"]'

describe('Pagination', () => {
	let richObject = {}

	before(() => {
		cy.fixture('widgets/richObject.json')
			.then(richObjectFixture => {
				richObject = richObjectFixture
			})
	})

	it('shows a footer pagination once the cards span more than one page', () => {
		mountTable(richObject, ROW_COUNT, CARD_LAYOUT)

		cy.get(TOOLBAR_PAGINATION).should('exist')
		cy.get(FOOTER_PAGINATION).should('exist')
	})

	it('hides the footer pagination while every card fits on one page', () => {
		mountTable(richObject, ROWS_PER_PAGE, CARD_LAYOUT)

		cy.get(TOOLBAR_PAGINATION).should('exist')
		cy.get(FOOTER_PAGINATION).should('not.exist')
	})

	// A card grid is tall enough to need a control at its end; the table layout is
	// unchanged by this feature and keeps the single one it has always had.
	it('leaves the table layout with the toolbar pagination alone', () => {
		mountTable(richObject, ROW_COUNT)

		cy.get(TOOLBAR_PAGINATION).should('exist')
		cy.get(FOOTER_PAGINATION).should('not.exist')
	})

	it('keeps the toolbar pagination in sync when paging from the footer', () => {
		// A gallery card lists the labelled column below its title, so the page it is on
		// can be read off the card itself.
		mountTable(richObject, ROW_COUNT, { layout: 'gallery' })

		cy.get(`${FOOTER_PAGINATION} ${PAGE_INPUT}`).should('have.value', '1')
		cy.get(TOOLBAR_PAGINATION).find(PAGE_INPUT).should('have.value', '1')

		cy.get(FOOTER_PAGINATION)
			.find('button[aria-label="Go to next page"]')
			.click()

		cy.get(TOOLBAR_PAGINATION).find(PAGE_INPUT).should('have.value', '2')
		cy.get(`${FOOTER_PAGINATION} ${PAGE_INPUT}`).should('have.value', '2')
		cy.get('[data-cy="galleryLayoutCard"]').first().should('contain', `Row ${ROWS_PER_PAGE + 1}`)
	})

	it('keeps the footer pagination in sync when paging from the toolbar', () => {
		mountTable(richObject, ROW_COUNT, CARD_LAYOUT)

		cy.get(TOOLBAR_PAGINATION)
			.find('button[aria-label="Go to last page"]')
			.click()

		cy.get(`${FOOTER_PAGINATION} ${PAGE_INPUT}`).should('have.value', '2')
		cy.get(TOOLBAR_PAGINATION).find(PAGE_INPUT).should('have.value', '2')
	})

	it('renders the footer pagination in the gallery layout', () => {
		mountTable(richObject, ROW_COUNT, { layout: 'gallery' })

		cy.get('[data-cy="galleryLayoutCard"]').should('have.length', ROWS_PER_PAGE)
		cy.get(FOOTER_PAGINATION).should('exist')
	})

	it('leaves a second table on the screen on its own page', () => {
		const rows = buildRows(richObject, ROW_COUNT)
		cy.mount({
			render() {
				return h('div', [
					h('div', { class: 'first' }, [
						h(NcTable, { rows, columns: richObject.columns, elementId: 1, isView: false, viewSetting: CARD_LAYOUT }),
					]),
					h('div', { class: 'second' }, [
						h(NcTable, { rows, columns: richObject.columns, elementId: 2, isView: false, viewSetting: CARD_LAYOUT }),
					]),
				])
			},
		})

		cy.get(`.first ${FOOTER_PAGINATION}`)
			.find('button[aria-label="Go to next page"]')
			.click()

		cy.get(`.first ${FOOTER_PAGINATION} ${PAGE_INPUT}`).should('have.value', '2')
		cy.get(`.second ${FOOTER_PAGINATION} ${PAGE_INPUT}`).should('have.value', '1')
	})

	it('falls back to a valid page when the row set shrinks', () => {
		mountTable(richObject, ROW_COUNT, CARD_LAYOUT).then(({ wrapper }) => {
			cy.get(FOOTER_PAGINATION)
				.find('button[aria-label="Go to last page"]')
				.click()

			cy.get(`${FOOTER_PAGINATION} ${PAGE_INPUT}`)
				.should('have.value', '2')
				.then(() => wrapper.setProps({ rows: buildRows(richObject, 10) }))

			cy.get(TOOLBAR_PAGINATION).find(PAGE_INPUT).should('have.value', '1')
			cy.get('[data-cy="tilesLayoutCard"]').should('have.length', 10)
		})
	})
})

/**
 * Builds a labelled row set of the requested size from the fixture.
 *
 * @param {object} richObject the widget fixture holding the column and row templates
 * @param {number} count how many rows to build
 * @return {Array} the generated rows
 */
function buildRows(richObject, count) {
	const [template] = richObject.rows
	const labelColumnId = richObject.columns[0].id

	return Array.from({ length: count }, (unused, index) => ({
		...template,
		id: 1000 + index,
		data: template.data.map(cell => (
			cell.columnId === labelColumnId
				? { ...cell, value: `Row ${index + 1}` }
				: { ...cell }
		)),
	}))
}

/**
 * Mounts NcTable with a generated row set.
 *
 * @param {object} richObject the widget fixture holding the column and row templates
 * @param {number} rowCount how many rows to render
 * @param {object} viewSetting the view setting to render with
 * @return {Cypress.Chainable} the mount result
 */
function mountTable(richObject, rowCount, viewSetting = {}) {
	return cy.mount(NcTable, {
		props: {
			rows: buildRows(richObject, rowCount),
			columns: richObject.columns,
			elementId: richObject.id,
			isView: false,
			viewSetting,
		},
	})
}
