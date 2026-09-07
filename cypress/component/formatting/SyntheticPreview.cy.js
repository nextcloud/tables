/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import SyntheticPreview from '../../../src/components/formatting/SyntheticPreview.vue'
import { FilterIds } from '../../../src/shared/components/ncTable/mixins/filter.js'

const CELL = '.synthetic-preview__cell'

/**
 * @param {object} condition columnId, operator and value of the condition
 * @param {Array} columns columns the condition may address
 */
function mountPreview(condition, columns = [{ id: 7, title: 'Amount' }]) {
	cy.mount(SyntheticPreview, {
		props: {
			conditionSet: { groups: [{ conditions: [condition] }] },
			format: { backgroundColor: '#ff0000' },
			columns,
		},
	})
}

describe('SyntheticPreview', () => {
	it('derives a matching and a non-matching value for a numeric operator', () => {
		mountPreview({ columnId: 7, operator: FilterIds.IsGreaterThan, value: '10' })

		cy.get(CELL).eq(0).should('contain.text', '11')
		cy.get(CELL).eq(1).should('contain.text', '9')
	})

	it('derives values for the equality operators', () => {
		mountPreview({ columnId: 7, operator: FilterIds.IsEqual, value: 'done' })

		cy.get(CELL).eq(0).should('contain.text', 'done')
		cy.get(CELL).eq(1).should('contain.text', 'done_other')
	})

	it('derives values for the text operators', () => {
		mountPreview({ columnId: 7, operator: FilterIds.Contains, value: 'abc' })

		cy.get(CELL).eq(0).should('contain.text', 'a abc b')
		cy.get(CELL).eq(1).should('contain.text', 'unrelated')
	})

	it('shows the option label rather than the stored selection reference', () => {
		mountPreview(
			{ columnId: 7, operator: FilterIds.IsEqual, value: '@selection-id-3' },
			[{ id: 7, title: 'Status', selectionOptions: [{ id: 3, label: 'Done' }] }],
		)

		cy.get(CELL).eq(0).should('contain.text', 'Done')
	})

	it('falls back to a placeholder instead of NaN when the value is not numeric yet', () => {
		mountPreview({ columnId: 7, operator: FilterIds.IsLowerThan, value: '' })

		cy.get(CELL).eq(0).should('contain.text', 'example')
	})
})
