/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import Icon from '../../src/shared/components/ncIconPicker/partials/Icon.vue'

describe('Icon picker icon', () => {
	it('names the button after its icon', () => {
		cy.mount(Icon, { props: { name: 'alarm' } })
		cy.get('button').should('have.attr', 'aria-label', 'Select icon Alarm')
	})

	it('gives different icons different names', () => {
		cy.mount(Icon, { props: { name: 'anchor' } })
		cy.get('button').should('have.attr', 'aria-label', 'Select icon Anchor')
	})
})
