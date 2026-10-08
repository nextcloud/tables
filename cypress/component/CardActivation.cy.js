/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import CustomTable from '../../src/shared/components/ncTable/sections/CustomTable.vue'
import { ColumnTypes } from '../../src/shared/components/ncTable/mixins/columnHandler.js'
import TextRichColumn from '../../src/shared/components/ncTable/mixins/columnsTypes/textRich.js'

const CARD = '[data-cy="galleryLayoutCard"]'
const TITLE = '[data-cy="galleryLayoutCardTitle"]'

describe('Card activation', () => {
	let columns = []
	let rows = []

	beforeEach(() => {
		columns = [
			new TextRichColumn({ id: 1, title: 'Notes', type: ColumnTypes.TextRich }),
			new TextRichColumn({ id: 2, title: 'More', type: ColumnTypes.TextRich }),
		]
		rows = [{
			id: 7,
			data: [
				{ columnId: 1, value: 'a card title' },
				{ columnId: 2, value: '[a link](https://example.invalid/page)' },
			],
		}]
	})

	it('is a plain container with an explicitly named control', () => {
		mountCards(columns, rows)

		// A button role would make the metadata list and its link presentational.
		cy.get(CARD).should('not.have.attr', 'role')
		cy.get(CARD).should('not.have.attr', 'tabindex')
		cy.get(CARD).should($card => expect($card[0].tagName).to.equal('DIV'))
		cy.get(TITLE).should($title => expect($title[0].tagName).to.equal('BUTTON'))
		// Named from the title element rather than carrying a second copy of the string,
		// so the control and the text it stands for cannot drift apart.
		cy.get(TITLE).should('not.have.attr', 'aria-label')
		cy.get(TITLE).then($button => {
			cy.get(`#${$button.attr('aria-labelledby')}`).should('contain', 'a card title')
		})
		// The title stays in the text flow, where the line clamp can act on it.
		cy.get(`${CARD} .layout-card__title-text`).should('contain', 'a card title')
	})

	it('renders a rich title and keeps its link on top of the control', () => {
		rows[0].data[0].value = '**bold** [t](https://example.invalid/p)'
		mountCards(columns, rows)

		cy.get(`${CARD} .layout-card__title-text strong`).should('exist')
		cy.get(`${CARD} .layout-card__title-banner a`).should($link => {
			const box = $link[0].getBoundingClientRect()
			const top = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2)
			// The overlay must not cover a link the title brings with it.
			expect(top.closest('a')).to.equal($link[0])
		})
	})

	it('keeps a link inside the card reachable', () => {
		mountCards(columns, rows)

		cy.get(`${CARD} a`).should('exist').and('have.attr', 'href')
	})

	it('opens the row once from the focusable control', () => {
		mountCards(columns, rows).then(({ wrapper }) => {
			// A real button, so the platform handles enter and space; what needs asserting
			// is that it can be reached and that activating it does not open the row twice
			// by also running the card's own handler.
			cy.get(TITLE).focus().should('be.focused')
			cy.get(TITLE).click({ force: true })
			.then(() => {
				expect(wrapper.emitted('edit-row')).to.deep.equal([[7]])
			})
		})
	})

	it('opens the row when the card body is clicked', () => {
		mountCards(columns, rows).then(({ wrapper }) => {
			cy.get(`${CARD} .layout-card__image-wrapper`).click({ force: true })
			.then(() => {
				expect(wrapper.emitted('edit-row')).to.deep.equal([[7]])
			})
		})
	})

	it('exposes the card image as content rather than decoration', () => {
		// The image says something the title does not, so it keeps a name and stays in the
		// accessibility tree; nothing else in the card repeats it.
		rows[0].data[1].value = '/index.php/f/42'
		mountCards(columns, rows, 2)

		cy.get(`${CARD} img.layout-card__image`).invoke('attr', 'alt').should('not.be.empty')
		cy.get(`${CARD} img.layout-card__image`).should('not.have.attr', 'aria-hidden')
		cy.get(`${CARD} img.layout-card__image`).should('not.have.attr', 'role')
	})

	it('follows a link inside a card without also opening the row', () => {
		mountCards(columns, rows).then(({ wrapper }) => {
			cy.get(`${CARD} a`).then($link => {
				// Keep the harness on the page; the click still bubbles as it would in the app.
				$link.on('click', event => event.preventDefault())
			})
			cy.get(`${CARD} a`).click({ force: true })
			.then(() => {
				expect(wrapper.emitted('edit-row')).to.equal(undefined)
			})
		})
	})
})

/**
 * Mounts CustomTable rendering the given rows as a gallery.
 *
 * @param {Array} columns the parsed columns
 * @param {Array} rows the rows to render
 * @param {number|null} backgroundColumnId the column a card reads its image from
 * @return {Cypress.Chainable} the mount result
 */
function mountCards(columns, rows, backgroundColumnId = null) {
	return cy.mount(CustomTable, {
		props: {
			columns,
			rows,
			elementId: 1,
			isView: true,
			layout: 'gallery',
			viewSettings: { cardBackgroundSource: backgroundColumnId, cardTitleSource: 1 },
			viewSetting: {},
			config: { canReadRows: true },
		},
	})
}
