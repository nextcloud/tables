/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import Icon from '../../src/shared/components/ncIconPicker/partials/Icon.vue'

describe('Icon picker icon', () => {
	it('names the button after its icon', () => {
		cy.mount(Icon, { props: { name: 'alarm' } })
		cy.get('button').should('have.attr', 'aria-label', 'Select icon Alarm clock')
	})

	it('gives different icons different names', () => {
		cy.mount(Icon, { props: { name: 'delete' } })
		cy.get('button').should('have.attr', 'aria-label', 'Select icon Trash can')
	})

	it('shows the icon name as a tooltip', () => {
		cy.mount(Icon, { props: { name: 'delete' } })
		cy.get('button').should('have.attr', 'title', 'Trash can')
	})

	it('does not HTML-escape the icon name', () => {
		cy.mount(Icon, { props: { name: 'it\'s' } })
		cy.get('button').should('have.attr', 'aria-label', 'Select icon it\'s')
	})
})
