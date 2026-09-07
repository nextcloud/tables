/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, setActivePinia } from 'pinia'
import NcTable from '../../../src/shared/components/ncTable/NcTable.vue'
import { useFormattingStore } from '../../../src/store/formatting.js'
import { useTablesStore } from '../../../src/store/store.js'

const VIEW_ID = 42
const NAME = 1
const SCORE = 2

const columns = [
	{ id: NAME, title: 'Name', type: 'text', subtype: 'line' },
	{ id: SCORE, title: 'Score', type: 'number', subtype: '' },
]

const rows = [
	{ id: 1, data: [{ columnId: NAME, value: 'high' }, { columnId: SCORE, value: 9 }] },
	{ id: 2, data: [{ columnId: NAME, value: 'low' }, { columnId: SCORE, value: 1 }] },
]

const ruleSets = [{
	id: 'rs-1',
	title: 'High score',
	targetType: 'column',
	targetCol: SCORE,
	mode: 'first-match',
	marker: 'star',
	sortOrder: 0,
	enabled: true,
	broken: false,
	rules: [{
		id: 'r-1',
		title: 'High score',
		sortOrder: 0,
		enabled: true,
		broken: false,
		condition: { groups: [{ conditions: [{ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 }] }] },
		format: { backgroundColor: '#ffd9d9' },
	}],
}]

describe('formatting on first render', () => {
	it('paints the rows of a view without any interaction', () => {
		const pinia = createPinia()
		setActivePinia(pinia)
		const tablesStore = useTablesStore()
		tablesStore.setViews([{ id: VIEW_ID, tableId: 1, title: 'View', formatting: ruleSets }])
		useFormattingStore().loadForView(VIEW_ID)

		cy.mount(NcTable, {
			props: { rows, columns, elementId: VIEW_ID, isView: true },
			global: { plugins: [pinia] },
		})

		cy.get('td').filter((index, el) => el.style.backgroundColor !== '').should('have.length', 1)
		cy.get('.formatting-marker').should('have.length', 1)
		cy.contains('Marked by rule set High score').should('exist')
	})
})
