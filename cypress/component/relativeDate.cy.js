/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { h } from 'vue'
import relativeDate from '../../src/shared/mixins/relativeDate.js'

const Probe = {
	mixins: [relativeDate],
	props: { timestamp: { type: Number, required: true } },
	render() {
		return h('span', { 'data-cy': 'relativeDate' }, this.relativeDate(this.timestamp))
	},
}

describe('relativeDate', () => {
	it('names a moment a few seconds ago without a global t()', () => {
		cy.window().then(win => {
			expect(win.t, 'no global translation function').to.equal(undefined)
		})
		cy.mount(Probe, { props: { timestamp: Date.now() - 5000 } })
		cy.get('[data-cy="relativeDate"]').should('have.text', 'seconds ago')
	})
})
