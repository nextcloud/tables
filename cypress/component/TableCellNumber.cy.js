/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setLocale } from '@nextcloud/l10n'

import TableCellNumber from '../../src/shared/components/ncTable/partials/TableCellNumber.vue'
import NumberForm from '../../src/shared/components/ncTable/partials/rowTypePartials/NumberForm.vue'

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
		mountNumberCell(1.2, { numberDecimals: 101 })

		cy.get('.number-display').invoke('text').should((text) => {
			expect(text).to.match(/^1\.20+$/)
			expect(text.trim().split('.')[1]).to.have.length(100)
		})
	})

	it('clamps out-of-range decimal counts when editing row values', () => {
		mountRowNumberForm(1.2, { numberDecimals: 101 }).then(({ wrapper }) => {
			expect(wrapper.vm.numberFractionDigits).to.equal(100)
			expect(wrapper.vm.parseValue('1.234')).to.equal(1.234)
			expect(wrapper.vm.getStep).to.match(/^\.0+1$/)

			const step = wrapper.vm.getStep
			expect(step.slice(1, -1)).to.have.length(99)
		})
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

function mountRowNumberForm(value, columnOverrides = {}) {
	return cy.mount(NumberForm, {
		props: {
			column: {
				id: 1,
				title: 'Amount',
				description: '',
				mandatory: false,
				numberDecimals: 0,
				numberDefault: undefined,
				numberMax: null,
				numberMin: null,
				numberPrefix: '',
				numberSuffix: '',
				viewColumnInformation: {},
				...columnOverrides,
			},
			value,
		},
		global: {
			components: {
				RowFormWrapper: {
					template: '<div><slot /></div>',
				},
			},
		},
	})
}
