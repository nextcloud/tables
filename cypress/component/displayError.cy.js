/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import displayError, { getGenericLoadError, getNotFoundError } from '../../src/shared/utils/displayError.js'

describe('displayError', () => {
	it('builds its messages without a global t()', () => {
		cy.window().then(win => {
			expect(win.t, 'no global translation function').to.equal(undefined)
		})
		expect(getNotFoundError('table')).to.equal('This table could not be found')
		expect(getGenericLoadError('view')).to.equal('An error occurred while loading the view')
	})

	it('reports a failed request with its status', () => {
		for (const status of [400, 401, 403, 404, 500]) {
			expect(() => displayError({ response: { status } }, 'Could not load the rows.')).not.to.throw()
		}
	})
})
