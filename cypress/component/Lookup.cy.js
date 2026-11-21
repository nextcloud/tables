/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { useDataStore } from '../../src/store/data.js'
import { useTablesStore } from '../../src/store/store.js'
import { parseCol } from '../../src/shared/components/ncTable/mixins/columnParser.js'
import LookupColumn from '../../src/shared/components/ncTable/mixins/columnsTypes/lookup.js'
import TableCellLookup from '../../src/shared/components/ncTable/partials/TableCellLookup.vue'
import LookupForm from '../../src/shared/components/ncTable/partials/columnTypePartials/forms/LookupForm.vue'
import NcTable from '../../src/shared/components/ncTable/NcTable.vue'
import rowHelper from '../../src/shared/components/ncTable/mixins/rowHelper.js'
import ColumnFormComponent from '../../src/modules/main/partials/ColumnFormComponent.vue'
import TableRow from '../../src/shared/components/ncTable/partials/TableRow.vue'
import TableCellTextLine from '../../src/shared/components/ncTable/partials/TableCellTextLine.vue'

const targetColumn = (overrides = {}) => ({
	id: 20,
	tableId: 2,
	title: 'City',
	type: 'text',
	subtype: 'line',
	mandatory: false,
	customSettings: {},
	...overrides,
})

const sourceRelation = {
	id: 7,
	tableId: 1,
	title: 'Person',
	type: 'relation',
	subtype: '',
	customSettings: { relationType: 'view', targetId: 2, labelColumn: 20 },
}

const sourceRow = (id, value) => ({ id, data: [{ columnId: 7, value }] })

let pinia
let store
let column

function mount(component, props) {
	return cy.mount(component, {
		props,
		global: {
			plugins: [pinia],
			mixins: [{ methods: { t, n } }],
			config: { globalProperties: { OC: window.OC, OCA: window.OCA } },
		},
	})
}

function setLookup(target, values, loading = false) {
	store.relations[8] = {
		column: parseCol(target),
		values: Object.fromEntries(Object.entries(values).map(([id, value]) => [id, { id: Number(id), value }])),
		loading,
	}
}

describe('Lookup behavior', () => {
	beforeEach(() => {
		pinia = createPinia()
		setActivePinia(pinia)
		store = useDataStore()
		column = new LookupColumn({
			id: 8,
			tableId: 1,
			title: 'City of person',
			customSettings: { relationColumnId: 7, targetColumnId: 20 },
		})
		store.columns['1'] = [column]
	})

	it('dispatches the lookup form for the lookup API type', () => {
		setLookup(targetColumn(), { 42: 'Berlin' })
		mount(ColumnFormComponent, { column, value: 42 })
		cy.contains('Berlin').should('be.visible')
		cy.then(() => expect(column.type).to.equal('lookup'))
	})

	it('renders a looked-up text value without an editor', () => {
		setLookup(targetColumn(), { 42: 'Berlin' })
		mount(TableCellLookup, { column, value: 42, rowId: 1 })
		cy.contains('Berlin').click()
		cy.get('input, textarea').should('not.exist')
		cy.then(() => expect(column.getValueString({ value: 42 })).to.equal('Berlin'))
	})

	for (const isView of [false, true]) {
		it(`blocks inline lookup editing in a fallback renderer (isView=${isView})`, () => {
			mount(TableCellTextLine, { column, value: 'Berlin', rowId: 1, canEdit: true, isView })
			cy.contains('Berlin').click()
			cy.get('input, textarea, .inline-editing-container').should('not.exist')
		})
	}

	it('keeps lookup clicks read-only while allowing inline editing of ordinary columns', () => {
		setLookup(targetColumn(), { 42: 'Berlin' })
		const ownColumn = parseCol(targetColumn({ id: 9, tableId: 1, title: 'Own value' }))
		const row = sourceRow(1, 42)
		row.data.push({ columnId: 9, value: 'Editable value' })
		const onEditRow = cy.stub().as('editRow')
		mount(TableRow, {
			row,
			columns: [column, ownColumn],
			config: { canEditRows: true, canSelectRows: false, showActions: false },
			isView: false,
			elementId: 1,
			onEditRow,
		})
		cy.contains('td', 'Berlin').click()
		cy.get('input, textarea, .inline-editing-container').should('not.exist')
		cy.get('@editRow').should('not.have.been.called')
		cy.contains('td', 'Editable value').click()
		cy.get('input').should('be.visible').and('have.value', 'Editable value')
	})

	it('keeps numeric lookup values typed and formats zero correctly', () => {
		setLookup(targetColumn({ type: 'number', subtype: '', numberDecimals: 2, numberPrefix: '$', numberSuffix: ' USD' }), { 42: 0 })
		mount(TableCellLookup, { column, value: 42, rowId: 1 })
		cy.get('.cell-number').should('contain', '0.00').and('contain', '$').and('contain', 'USD').and('not.contain', 'NaN')
		cy.then(() => { store.relations[8].values[42].value = 12.5 })
		cy.get('.cell-number').should('contain', '12.50')
		cy.then(() => expect(column.getValueString({ value: 42 })).to.equal(12.5))
	})

	it('distinguishes loading, unavailable, and empty values without exporting row IDs', () => {
		setLookup(targetColumn(), { 42: 'Old value' }, true)
		mount(TableCellLookup, { column, value: 42, rowId: 1 })
		cy.get('[aria-label="Loading lookup value"]').should('exist')
		cy.contains('Old value').should('not.exist')
		cy.then(() => { store.relations[8] = { column: null, values: {}, loading: false } })
		cy.contains('Unavailable').should('be.visible')
		cy.then(() => {
			expect(column.getValueString({ value: 42 })).to.equal('')
			expect(column.resolveValue(null).status).to.equal('unavailable')
			expect(column.isSearchStringFound({ value: 42 }, '42')).to.equal(false)
		})
	})

	it('renders an empty target value without treating the reference as unavailable', () => {
		setLookup(targetColumn(), { 42: null })
		mount(TableCellLookup, { column, value: 42, rowId: 1 })
		cy.contains('Unavailable').should('not.exist')
		cy.then(() => {
			expect(column.getValueString({ value: 42 })).to.equal('')
			expect(column.resolveValue(null).status).to.equal('empty')
		})
	})

	it('searches and sorts lookup-only table columns through hidden relation cells', () => {
		setLookup(targetColumn(), { 42: 'Zurich', 43: 'Amsterdam', 44: 'Berlin' })
		mount(NcTable, {
			columns: [column],
			rows: [sourceRow(1, 42), sourceRow(2, 43), sourceRow(3, 44)],
			viewSetting: { searchString: 'berlin', sorting: [{ columnId: 8, mode: 'ASC' }] },
			showActions: false,
			canSelectRows: false,
		})
		cy.get('[data-cy="customTableRow"]').should('have.length', 1).and('contain', 'Berlin')
		cy.then(() => {
			const rows = [sourceRow(1, 42), sourceRow(2, 43)]
			expect(rows.sort(column.sort('ASC')).map(row => row.id)).to.deep.equal([2, 1])
			expect(rows.sort(column.sort('DESC')).map(row => row.id)).to.deep.equal([1, 2])
		})
	})

	it('sorts numeric lookups numerically, retaining zero and secondary sorting', () => {
		setLookup(targetColumn({ type: 'number', subtype: '' }), { 42: 10, 43: 2, 44: 0, 45: -1, 46: 2 })
		cy.then(() => {
			const rows = [sourceRow(1, 42), sourceRow(2, 43), sourceRow(3, 44), sourceRow(4, 45), sourceRow(5, 46)]
			const secondary = (a, b) => b.id - a.id
			expect(rows.sort(column.sort('ASC', [secondary])).map(row => row.id)).to.deep.equal([4, 3, 5, 2, 1])
		})
	})

	it('ignores legacy mandatory flags on virtual lookup columns', () => {
		column.mandatory = true
		column.viewColumnInformation = { mandatory: true }
		cy.then(() => {
			const helper = { ...rowHelper.methods, columns: [column] }
			expect(helper.isMandatory(column)).to.equal(false)
			expect(helper.checkMandatoryFields({ 7: 42 })).to.equal(false)
		})
	})

	it('configures lookups from a source view without requiring the target view in navigation', () => {
		const tables = useTablesStore()
		tables.views = [{ id: 3, tableId: 1 }]
		tables.setActiveViewId(3)
		store.columns = {}
		cy.intercept('GET', '**/apps/tables/api/1/tables/1/columns?viewId=3', [sourceRelation]).as('sourceColumns')
		cy.intercept('GET', '**/apps/tables/api/1/views/2/columns', [
			targetColumn(),
			targetColumn({ id: 21, type: 'relation', subtype: '', title: 'Nested relation' }),
			targetColumn({ id: 22, type: 'lookup', subtype: '', title: 'Nested lookup' }),
		]).as('targetColumns')
		mount(LookupForm, { column })
		cy.wait(['@sourceColumns', '@targetColumns'])
		cy.get('[aria-label="Select target column"]').click()
		cy.get('.vs__dropdown-menu').should('contain', 'City').and('not.contain', 'Nested relation').and('not.contain', 'Nested lookup')
	})

	it('loads relation data through the public endpoint and clears omitted entries on refresh', () => {
		const token = 'abcdefghijklmnop'
		store.setPublicToken(token)
		store.columns['public-' + token] = [column]
		let response = { 8: { column: targetColumn(), values: { 42: { id: 42, value: 'Berlin' } } } }
		cy.intercept('GET', '**/apps/tables/api/2/public/' + token + '/relations*', req => {
			req.reply({ ocs: { data: response } })
		}).as('publicRelations')
		cy.then(() => store.loadRelationsFromBE({ token }))
		cy.then(() => {
			expect(column.getValueString({ value: 42 })).to.equal('Berlin')
			response = {}
		})
		cy.then(() => store.loadRelationsFromBE({ token, force: true }))
		cy.then(() => {
			expect(column.resolveValue(42).status).to.equal('unavailable')
			expect(column.getValueString({ value: 42 })).to.equal('')
			expect(store.getRelationsLoading(false, null)).to.equal(false)
		})
	})
})
