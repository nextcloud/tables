/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setLocale } from '@nextcloud/l10n'

import TableCellNumber from '../../src/shared/components/ncTable/partials/TableCellNumber.vue'

describe('TableCellNumber', () => {
	beforeEach(() => {
		setLocale('en_US')
	})

	it('displays decimals with the current locale separator', () => {
		setLocale('de_DE')

		mountNumberCell(1.234, { numberDecimals: 3 })

		cy.get('.number-display').should('contain.text', '1,234')
	})

	it('keeps the configured amount of decimal places', () => {
		mountNumberCell(1.2, { numberDecimals: 2 })

		cy.get('.number-display').should('contain.text', '1.20')
	})

	it('clamps out-of-range decimal counts for Intl.NumberFormat', () => {
		mountNumberCell(1.234567, { numberDecimals: 50 })

		cy.get('.number-display').should('contain.text', '1.234567')
	})
})

function mountNumberCell(value, columnOverrides = {}) {
	cy.mount(TableCellNumber, {
		props: {
			column: {
				id: 1,
				numberDecimals: 0,
				numberMax: null,
				numberMin: null,
				numberPrefix: '',
				numberSuffix: '',
				...columnOverrides,
			},
			rowId: 1,
			value,
		},
	})
}
