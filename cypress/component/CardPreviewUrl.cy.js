/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import CustomTable from '../../src/shared/components/ncTable/sections/CustomTable.vue'
import { ColumnTypes } from '../../src/shared/components/ncTable/mixins/columnHandler.js'
import TextLineColumn from '../../src/shared/components/ncTable/mixins/columnsTypes/textLine.js'

const CARD = '[data-cy="tilesLayoutCard"]'

describe('Card preview URL', () => {
	const columns = [
		new TextLineColumn({ id: 1, title: 'Name', type: ColumnTypes.TextLine }),
		new TextLineColumn({ id: 2, title: 'Image', type: ColumnTypes.TextLine }),
	]

	it('takes the file id from a same origin link, whatever its spelling', () => {
		mountCards('x').then(({ wrapper }) => {
			const origin = window.location.origin
			const id = value => wrapper.vm.getLocalFileId(value)

			expect(id(`${origin}/index.php/f/123`)).to.equal('123')
			expect(id(`${origin}/index.php/f/123/`)).to.equal('123')
			expect(id('/index.php/f/456')).to.equal('456', 'a relative link resolves against this origin')
			expect(id(`${origin}/index.php/core/preview?fileId=7&x=9999`)).to.equal('7')
			expect(id(`${origin}/apps/files/?FILEID=8`)).to.equal('8', 'the parameter name is not case sensitive')
		})
	})

	it('refuses anything that does not name a file on this server', () => {
		mountCards('x').then(({ wrapper }) => {
			const id = value => wrapper.vm.getLocalFileId(value)

			expect(id('https://evil.example/index.php/f/42')).to.equal(null, 'another host is never followed')
			expect(id('https://evil.example/index.php/core/preview?fileId=42')).to.equal(null)
			expect(id('not a link')).to.equal(null)
			expect(id(`${window.location.origin}/index.php/f/abc`)).to.equal(null, 'the id has to be numeric')
		})
	})

	it('rebuilds the request instead of forwarding the stored one', () => {
		mountCards(`${window.location.origin}/index.php/core/preview?fileId=7&x=9999&evil=1`)

		cy.get(`${CARD} img`).should('have.attr', 'src').then(src => {
			expect(src).to.match(/\/core\/preview\?fileId=7&x=\d+&y=\d+&a=true$/)
			expect(src).not.to.contain('evil')
			expect(src).not.to.contain('9999')
		})
	})

	it('reads a link cell stored as JSON', () => {
		mountCards(JSON.stringify({ value: `${window.location.origin}/index.php/f/55`, title: 'x' }))

		cy.get(`${CARD} img`).should('have.attr', 'src').and('match', /fileId=55&/)
	})

	it('renders no image for a value it will not follow', () => {
		mountCards('https://evil.example/index.php/f/42')

		cy.get(CARD).should('exist')
		cy.get(`${CARD} img`).should('not.exist')
	})

	/**
	 * Mounts one tile whose background cell holds the given value.
	 *
	 * @param {string} imageValue the raw value of the image cell
	 * @return {Cypress.Chainable} the mount result
	 */
	function mountCards(imageValue) {
		return cy.mount(CustomTable, {
			props: {
				columns,
				rows: [{ id: 1, data: [{ columnId: 1, value: 'a title' }, { columnId: 2, value: imageValue }] }],
				elementId: 1,
				isView: true,
				layout: 'tiles',
				viewSettings: { cardBackgroundSource: 2, cardTitleSource: 1 },
				viewSetting: {},
				config: { canReadRows: true },
			},
		})
	}
})
