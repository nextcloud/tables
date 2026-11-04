/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, setActivePinia } from 'pinia'
import { parseCol } from '../../../src/shared/components/ncTable/mixins/columnParser.js'
import { toCSS, useFormattingStore } from '../../../src/store/formatting.js'
import { useTablesStore } from '../../../src/store/store.js'

const NAME = 1
const SCORE = 2
const STATUS = 3
const DUE = 4
const DONE = 5

const columns = [
	parseCol({ id: NAME, title: 'Name', type: 'text', subtype: 'line' }),
	parseCol({ id: SCORE, title: 'Score', type: 'number', subtype: '' }),
	parseCol({
		id: STATUS,
		title: 'Status',
		type: 'selection',
		subtype: '',
		selectionOptions: [{ id: 7, label: 'Done' }, { id: 8, label: 'Open' }],
	}),
	parseCol({ id: DUE, title: 'Due', type: 'datetime', subtype: 'date' }),
	parseCol({ id: DONE, title: 'Done', type: 'selection', subtype: 'check' }),
]

/**
 * @param {number} id row id
 * @param {object} values map of columnId to cell value
 * @return {object}
 */
function row(id, values) {
	return {
		id,
		data: Object.entries(values).map(([columnId, value]) => ({ columnId: Number(columnId), value })),
	}
}

/**
 * @param {object} condition condition fields (columnId, columnType, operator, value)
 * @param {object} format style to apply
 * @param {object} overrides rule field overrides
 * @return {object}
 */
function rule(condition, format = { backgroundColor: '#ff0000' }, overrides = {}) {
	return {
		id: 'rule-' + Math.random().toString(36).slice(2, 8),
		title: 'rule',
		sortOrder: 0,
		enabled: true,
		broken: false,
		condition: { groups: [{ conditions: [condition] }] },
		format,
		...overrides,
	}
}

/**
 * @param {Array} rules rules of the set
 * @param {object} overrides rule set field overrides
 * @return {object}
 */
function ruleSet(rules, overrides = {}) {
	return {
		id: 'rs-' + Math.random().toString(36).slice(2, 8),
		title: 'set',
		targetType: 'row',
		targetCol: null,
		mode: 'first-match',
		sortOrder: 0,
		enabled: true,
		broken: false,
		rules,
		...overrides,
	}
}

/**
 * @param {Array} ruleSets rule sets to evaluate
 * @param {Array} rows rows to evaluate
 * @return {Array<number>}
 */
function styledRows(ruleSets, rows, cols = columns) {
	const store = useFormattingStore()
	store.applyRuleSets(ruleSets)
	store.evaluate(rows, cols)
	return rows.filter(r => store.rowStyle(r.id).backgroundColor !== undefined).map(r => r.id)
}

describe('formatting store evaluation', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	describe('operators', () => {
		const cases = [
			['is-empty', { columnId: NAME, columnType: 'text-line', operator: 'is-empty' }, { [NAME]: '' }, { [NAME]: 'x' }],
			['is-not-empty', { columnId: NAME, columnType: 'text-line', operator: 'is-not-empty' }, { [NAME]: 'x' }, { [NAME]: '' }],
			['is-equal on text', { columnId: NAME, columnType: 'text-line', operator: 'is-equal', value: 'Alice' }, { [NAME]: 'Alice' }, { [NAME]: 'Bob' }],
			['is-not-equal on text', { columnId: NAME, columnType: 'text-line', operator: 'is-not-equal', value: 'Alice' }, { [NAME]: 'Bob' }, { [NAME]: 'Alice' }],
			['contains', { columnId: NAME, columnType: 'text-line', operator: 'contains', value: 'ali' }, { [NAME]: 'Malice' }, { [NAME]: 'Bob' }],
			['does-not-contain', { columnId: NAME, columnType: 'text-line', operator: 'does-not-contain', value: 'ali' }, { [NAME]: 'Bob' }, { [NAME]: 'Malice' }],
			['begins-with', { columnId: NAME, columnType: 'text-line', operator: 'begins-with', value: 'al' }, { [NAME]: 'Alice' }, { [NAME]: 'Malice' }],
			['ends-with', { columnId: NAME, columnType: 'text-line', operator: 'ends-with', value: 'ice' }, { [NAME]: 'Alice' }, { [NAME]: 'Alicia' }],
			['is-greater-than', { columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 10 }, { [SCORE]: 11 }, { [SCORE]: 10 }],
			['is-greater-than-or-equal', { columnId: SCORE, columnType: 'number', operator: 'is-greater-than-or-equal', value: 10 }, { [SCORE]: 10 }, { [SCORE]: 9 }],
			['is-lower-than', { columnId: SCORE, columnType: 'number', operator: 'is-lower-than', value: 10 }, { [SCORE]: 9 }, { [SCORE]: 10 }],
			['is-lower-than-or-equal', { columnId: SCORE, columnType: 'number', operator: 'is-lower-than-or-equal', value: 10 }, { [SCORE]: 10 }, { [SCORE]: 11 }],
		]

		cases.forEach(([name, condition, matching, nonMatching]) => {
			it(name, () => {
				const rows = [row(1, matching), row(2, nonMatching)]
				expect(styledRows([ruleSet([rule(condition)])], rows)).to.deep.equal([1])
			})
		})

		it('matches a selection column by its option label, like the view filter does', () => {
			const rows = [row(1, { [STATUS]: 7 }), row(2, { [STATUS]: 8 })]
			const condition = { columnId: STATUS, columnType: 'selection', operator: 'is-equal', value: 'Done' }
			expect(styledRows([ruleSet([rule(condition)])], rows)).to.deep.equal([1])
		})

		it('resolves a stored @selection-id- reference to its option', () => {
			const rows = [row(1, { [STATUS]: 7 }), row(2, { [STATUS]: 8 })]
			const condition = { columnId: STATUS, columnType: 'selection', operator: 'is-equal', value: '@selection-id-7' }
			expect(styledRows([ruleSet([rule(condition)])], rows)).to.deep.equal([1])
		})

		it('tells two options with the same label apart', () => {
			const sameLabel = [{ ...columns[1], selectionOptions: [{ id: 7, label: 'Done' }, { id: 9, label: 'Done' }] }]
			const cols = [columns[0], sameLabel[0], ...columns.slice(2)]
			const rows = [row(1, { [STATUS]: 7 }), row(2, { [STATUS]: 9 })]
			const condition = { columnId: STATUS, columnType: 'selection', operator: 'is-equal', value: '@selection-id-7' }
			expect(styledRows([ruleSet([rule(condition)])], rows, cols)).to.deep.equal([1])
		})

		it('matches nothing when the referenced option is gone', () => {
			const rows = [row(1, { [STATUS]: 7 }), row(2, { [STATUS]: 8 })]
			const condition = { columnId: STATUS, columnType: 'selection', operator: 'is-equal', value: '@selection-id-99' }
			expect(styledRows([ruleSet([rule(condition)])], rows)).to.deep.equal([])
		})

		it('matches a date column against a date value', () => {
			const rows = [row(1, { [DUE]: '2026-01-09' }), row(2, { [DUE]: '2026-01-11' })]
			const condition = { columnId: DUE, columnType: 'datetime-date', operator: 'is-lower-than', value: '2026-01-10' }
			expect(styledRows([ruleSet([rule(condition)])], rows)).to.deep.equal([1])
		})

		it('never matches an operator the backend no longer accepts', () => {
			const rows = [row(1, { [NAME]: 'x' })]
			const condition = { columnId: NAME, columnType: 'text-line', operator: 'eq', value: 'x' }
			expect(styledRows([ruleSet([rule(condition)])], rows)).to.deep.equal([])
		})

		it('never matches a condition whose column is gone from the view', () => {
			const rows = [row(1, { [NAME]: 'x' })]
			const condition = { columnId: 9999, columnType: 'text-line', operator: 'contains', value: 'x' }
			expect(styledRows([ruleSet([rule(condition)])], rows)).to.deep.equal([])
		})

		it('never matches an operator the column type does not implement', () => {
			const rows = [row(1, { [NAME]: 'x' })]
			const condition = { columnId: NAME, columnType: 'text-line', operator: 'is-greater-than', value: 'a' }
			expect(styledRows([ruleSet([rule(condition)])], rows)).to.deep.equal([])
		})

		it('leaves the row data untouched while evaluating', () => {
			const rows = [row(1, { [NAME]: 'Alice' })]
			styledRows([ruleSet([rule({ columnId: NAME, columnType: 'text-line', operator: 'contains', value: 'ali' })])], rows)
			expect(rows[0].data[0]).to.not.have.property('filterFound')
		})
	})

	describe('condition groups', () => {
		it('requires every condition inside a group', () => {
			const r = rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 })
			r.condition.groups[0].conditions.push({ columnId: NAME, columnType: 'text-line', operator: 'is-equal', value: 'Alice' })
			const rows = [
				row(1, { [SCORE]: 9, [NAME]: 'Alice' }),
				row(2, { [SCORE]: 9, [NAME]: 'Bob' }),
				row(3, { [SCORE]: 1, [NAME]: 'Alice' }),
			]
			expect(styledRows([ruleSet([r])], rows)).to.deep.equal([1])
		})

		it('matches when any group matches', () => {
			const r = rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 })
			r.condition.groups.push({ conditions: [{ columnId: NAME, columnType: 'text-line', operator: 'is-equal', value: 'Alice' }] })
			const rows = [
				row(1, { [SCORE]: 9, [NAME]: 'Bob' }),
				row(2, { [SCORE]: 1, [NAME]: 'Alice' }),
				row(3, { [SCORE]: 1, [NAME]: 'Bob' }),
			]
			expect(styledRows([ruleSet([r])], rows)).to.deep.equal([1, 2])
		})

		it('ignores an empty group instead of matching everything', () => {
			const r = rule({ columnId: NAME, columnType: 'text-line', operator: 'contains', value: 'zzz' })
			r.condition.groups = [{ conditions: [] }]
			expect(styledRows([ruleSet([r])], [row(1, { [NAME]: 'Alice' })])).to.deep.equal([])
		})
	})

	describe('targets and modes', () => {
		it('row target styles every cell, column target only its own column', () => {
			const store = useFormattingStore()
			const match = { columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 }
			store.applyRuleSets([
				ruleSet([rule(match, { backgroundColor: '#ff0000' })]),
				ruleSet([rule(match, { textColor: '#00ff00' })], { targetType: 'column', targetCol: NAME, sortOrder: 1 }),
			])
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.rowStyle(1).backgroundColor).to.equal('#ff0000')
			expect(store.rowStyle(1).color).to.equal(undefined)
			expect(store.cellStyle(1, NAME).backgroundColor).to.equal('#ff0000')
			expect(store.cellStyle(1, NAME).color).to.equal('#00ff00')
			expect(store.cellStyle(1, SCORE).color).to.equal(undefined)
		})

		it('first-match stops at the first matching rule', () => {
			const store = useFormattingStore()
			store.applyRuleSets([ruleSet([
				rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 }, { backgroundColor: '#ff0000' }),
				rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 1 }, { backgroundColor: '#00ff00', fontWeight: 'bold' }),
			], { mode: 'first-match' })])
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.rowStyle(1)).to.deep.include({ backgroundColor: '#ff0000' })
			expect(store.rowStyle(1).fontWeight).to.equal(undefined)
		})

		it('all-matches merges every matching rule, later rules winning', () => {
			const store = useFormattingStore()
			store.applyRuleSets([ruleSet([
				rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 }, { backgroundColor: '#ff0000' }),
				rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 1 }, { backgroundColor: '#00ff00', fontWeight: 'bold' }),
			], { mode: 'all-matches' })])
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.rowStyle(1)).to.deep.include({ backgroundColor: '#00ff00', fontWeight: '700' })
		})

		it('skips disabled and broken rule sets and rules', () => {
			const store = useFormattingStore()
			const match = { columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 }
			store.applyRuleSets([
				ruleSet([rule(match)], { enabled: false }),
				ruleSet([rule(match)], { broken: true }),
				ruleSet([rule(match, { backgroundColor: '#ff0000' }, { enabled: false })]),
				ruleSet([rule(match, { backgroundColor: '#ff0000' }, { broken: true })]),
			])
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.rowStyle(1)).to.deep.equal(toCSS({}))
		})

		it('drops the style of a row that stops matching', () => {
			const store = useFormattingStore()
			store.applyRuleSets([ruleSet([rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 })])])
			store.evaluate([row(1, { [SCORE]: 9 })], columns)
			expect(store.rowStyle(1).backgroundColor).to.equal('#ff0000')

			store.evaluate([row(1, { [SCORE]: 1 })], columns)
			expect(store.rowStyle(1).backgroundColor).to.equal(undefined)
		})
	})

	describe('loading a view', () => {
		it('reads the rules of the view being shown from the tables store', () => {
			const tablesStore = useTablesStore()
			tablesStore.views = [{ id: 7, formatting: [ruleSet([])] }]
			const store = useFormattingStore()

			store.loadForView(7)

			expect(store.ruleSets).to.have.length(1)
		})

		it('sees a flag the server set without being reloaded', () => {
			const tablesStore = useTablesStore()
			tablesStore.views = [{ id: 7, formatting: [ruleSet([])] }]
			const store = useFormattingStore()
			store.loadForView(7)

			tablesStore.views[0].formatting[0].broken = true

			expect(store.ruleSets[0].broken).to.equal(true)
		})

		it('holds a link share\'s rules itself, having no view in the tables store', () => {
			const store = useFormattingStore()

			store.loadForPublicView([ruleSet([])])

			expect(store.ruleSets).to.have.length(1)
		})

		it('forgets an open manager when the store is cleared', () => {
			const store = useFormattingStore()
			store.showFormattingManager = true

			store.clear()

			expect(store.showFormattingManager).to.equal(false)
		})
	})

	describe('markers', () => {
		const match = { columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 }

		it('reports no markers for a rule set that sets none', () => {
			const store = useFormattingStore()
			store.applyRuleSets([ruleSet([rule(match)])])
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.cellMarkers(1, SCORE, true)).to.deep.equal([])
		})

		it('puts a column rule set\'s marker in the cell it paints', () => {
			const store = useFormattingStore()
			store.applyRuleSets([
				ruleSet([rule(match)], { targetType: 'column', targetCol: NAME, marker: 'flag', title: 'Overdue' }),
			])
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.cellMarkers(1, NAME, false)).to.deep.equal([{ marker: 'flag', title: 'Overdue' }])
			expect(store.cellMarkers(1, SCORE, false)).to.deep.equal([])
		})

		it('puts a row rule set\'s marker only in the first visible cell', () => {
			const store = useFormattingStore()
			store.applyRuleSets([ruleSet([rule(match)], { marker: 'star', title: 'Hot' })])
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.cellMarkers(1, NAME, true)).to.deep.equal([{ marker: 'star', title: 'Hot' }])
			expect(store.cellMarkers(1, NAME, false)).to.deep.equal([])
		})

		it('gives a row that matches nothing no markers at all', () => {
			const store = useFormattingStore()
			store.applyRuleSets([ruleSet([rule(match)], { marker: 'star', title: 'Hot' })])
			store.evaluate([row(1, { [SCORE]: 1 })], columns)

			expect(store.cellMarkers(1, NAME, true)).to.deep.equal([])
		})
	})

	describe('toCSS', () => {
		it('maps the style object to CSS properties', () => {
			expect(toCSS({
				backgroundColor: '#112233',
				textColor: '#ffffff',
				fontWeight: 'bold',
				fontStyle: 'italic',
				textDecoration: 'strikethrough',
			})).to.deep.equal({
				backgroundColor: '#112233',
				color: '#ffffff',
				fontWeight: '700',
				fontStyle: 'italic',
				textDecoration: 'line-through',
			})
		})

		it('maps underline and leaves unset properties undefined', () => {
			const css = toCSS({ textDecoration: 'underline' })
			expect(css.textDecoration).to.equal('underline')
			expect(css.backgroundColor).to.equal(undefined)
			expect(css.fontWeight).to.equal(undefined)
		})

		it('emits no key at all for a property the style does not set', () => {
			expect(Object.keys(toCSS({ textDecoration: 'underline' }))).to.deep.equal(['textDecoration'])
			expect(Object.keys(toCSS({}))).to.deep.equal([])
		})

		it('leaves a pinned column background alone when the row carries no style', () => {
			const store = useFormattingStore()
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			const merged = { backgroundColor: 'inherit', ...store.cellStyle(1, SCORE) }

			expect(merged.backgroundColor).to.equal('inherit')
		})

		it('returns an empty object for a missing style', () => {
			expect(toCSS(null)).to.deep.equal({})
		})
	})

	describe('rule set precedence', () => {
		it('lets the earlier rule set win over a later one on the same target', () => {
			const store = useFormattingStore()
			const match = { columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 1 }
			store.applyRuleSets([
				ruleSet([rule(match, { backgroundColor: '#111111' })], { sortOrder: 0 }),
				ruleSet([rule(match, { backgroundColor: '#222222' })], { sortOrder: 1 }),
			])
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.rowStyle(1).backgroundColor).to.equal('#111111')
		})
	})

	describe('meta columns', () => {
		it('matches a condition on a meta column the view does not list', () => {
			const rows = [
				{ ...row(1, {}), createdBy: 'alice' },
				{ ...row(2, {}), createdBy: 'bob' },
			]
			const condition = { columnId: -2, columnType: 'text-line', operator: 'is-equal', value: 'alice' }

			expect(styledRows([ruleSet([rule(condition)])], rows)).to.deep.equal([1])
		})

		it('reads the last editor from the field the API delivers', () => {
			const rows = [
				{ ...row(1, {}), lastEditBy: 'alice' },
				{ ...row(2, {}), lastEditBy: 'bob' },
			]
			const condition = { columnId: -3, columnType: 'text-line', operator: 'is-equal', value: 'alice' }

			expect(styledRows([ruleSet([rule(condition)])], rows)).to.deep.equal([1])
		})
	})

	describe('view scoping', () => {
		it('reports that its rules belong only to the view they were loaded for', () => {
			const store = useFormattingStore()
			store.viewId = 42

			expect(store.appliesTo(42)).to.equal(true)
			expect(store.appliesTo(43)).to.equal(false)
			expect(store.appliesTo(null)).to.equal(false)
		})

		it('matches a link share, which holds its rules under no view id', () => {
			const store = useFormattingStore()
			store.loadForPublicView([])

			expect(store.appliesTo(null)).to.equal(true)
			expect(store.appliesTo(42)).to.equal(false)
		})
	})
})
