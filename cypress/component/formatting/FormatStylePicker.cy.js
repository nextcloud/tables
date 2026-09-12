/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import FormatStylePicker from '../../../src/components/formatting/FormatStylePicker.vue'

const BACKGROUND_HEX = '.format-style-picker__row:nth-of-type(1) .format-style-picker__hex-input'
const TEXT_HEX = '.format-style-picker__row:nth-of-type(2) .format-style-picker__hex-input'
const WARNING = '.format-style-picker__contrast-warning'

/**
 * Mount the picker with a spy on the update:format event.
 *
 * @param {object} format initial style
 */
function mountPicker(format = {}) {
	const onUpdate = cy.spy().as('update')
	cy.mount(FormatStylePicker, {
		props: {
			format,
			'onUpdate:format': onUpdate,
		},
	})
}

describe('FormatStylePicker', () => {
	it('renders the initial style', () => {
		mountPicker({ backgroundColor: '#123456', textColor: '#ffffff', fontWeight: 'bold' })

		cy.get(BACKGROUND_HEX).should('have.value', '#123456')
		cy.get(TEXT_HEX).should('have.value', '#ffffff')
		cy.get('button[aria-label="Bold"]').should('have.attr', 'aria-pressed', 'true')
	})

	it('toggles bold, italic, strikethrough and underline and emits the style', () => {
		mountPicker()

		cy.get('button[aria-label="Bold"]').click()
		cy.get('@update').should('have.been.calledWith', { fontWeight: 'bold' })

		cy.get('button[aria-label="Italic"]').click()
		cy.get('@update').should('have.been.calledWith', { fontWeight: 'bold', fontStyle: 'italic' })

		cy.get('button[aria-label="Strikethrough"]').click()
		cy.get('@update').should('have.been.calledWith', { fontWeight: 'bold', fontStyle: 'italic', textDecoration: 'strikethrough' })

		// underline replaces strikethrough: only one decoration is stored
		cy.get('button[aria-label="Underline"]').click()
		cy.get('@update').should('have.been.calledWith', { fontWeight: 'bold', fontStyle: 'italic', textDecoration: 'underline' })

		// toggling again clears the property
		cy.get('button[aria-label="Bold"]').click()
		cy.get('@update').should('have.been.calledWith', { fontStyle: 'italic', textDecoration: 'underline' })
	})

	it('picks black text on a light background and white text on a dark one', () => {
		mountPicker()

		cy.get(BACKGROUND_HEX).clear().type('#ffffcc').blur()
		cy.get('@update').should('have.been.calledWith', { backgroundColor: '#ffffcc', textColor: '#000000' })
		cy.get(TEXT_HEX).should('have.value', '#000000')

		cy.get(BACKGROUND_HEX).clear().type('#102030').blur()
		cy.get('@update').should('have.been.calledWith', { backgroundColor: '#102030', textColor: '#ffffff' })
	})

	it('keeps a manually chosen text color and warns when the contrast is below 4.5:1', () => {
		mountPicker()

		cy.get(BACKGROUND_HEX).clear().type('#ffffff').blur()
		cy.get(WARNING).should('not.exist')

		cy.get(TEXT_HEX).clear().type('#cccccc').blur()
		cy.get('@update').should('have.been.calledWith', { backgroundColor: '#ffffff', textColor: '#cccccc' })
		cy.get(WARNING).should('be.visible')

		// a later background change must not overwrite the user's text color
		cy.get(BACKGROUND_HEX).clear().type('#000000').blur()
		cy.get(TEXT_HEX).should('have.value', '#cccccc')
		cy.get(WARNING).should('not.exist')
	})

	it('ignores invalid hex input and clears colors through the clear buttons', () => {
		mountPicker({ backgroundColor: '#ff0000', textColor: '#ffffff' })

		cy.get(BACKGROUND_HEX).clear().type('red').blur()
		cy.get('@update').should('not.have.been.called')

		cy.get('button[aria-label="Clear background color"]').click()
		cy.get('@update').should('have.been.calledWith', { textColor: '#ffffff' })

		cy.get('button[aria-label="Clear text color"]').click()
		cy.get('@update').should('have.been.calledWith', {})
	})
})
