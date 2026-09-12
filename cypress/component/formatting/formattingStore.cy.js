/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, setActivePinia } from 'pinia'
import { parseCol } from '../../../src/shared/components/ncTable/mixins/columnParser.js'
import { toCSS, useFormattingStore } from '../../../src/store/formatting.js'

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
 * Build a row in the shape the data store hands to the formatting store.
 *
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
 * Evaluate the rule sets against the rows and return the ids of the styled rows.
 *
 * @param {Array} ruleSets rule sets to evaluate
 * @param {Array} rows rows to evaluate
 * @return {Array<number>}
 */
function styledRows(ruleSets, rows) {
	const store = useFormattingStore()
	store.ruleSets = ruleSets
	store.evaluate(rows, columns)
	return rows.filter(r => store.rowStyle(r.id).backgroundColor !== undefined).map(r => r.id)
}

describe('formatting store evaluation', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	describe('operators', () => {
		// the operators the view filter editor produces, which is what builds the conditions
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
			store.ruleSets = [
				ruleSet([rule(match, { backgroundColor: '#ff0000' })]),
				ruleSet([rule(match, { textColor: '#00ff00' })], { targetType: 'column', targetCol: NAME, sortOrder: 1 }),
			]
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.rowStyle(1).backgroundColor).to.equal('#ff0000')
			expect(store.rowStyle(1).color).to.equal(undefined)
			expect(store.cellStyle(1, NAME).backgroundColor).to.equal('#ff0000')
			expect(store.cellStyle(1, NAME).color).to.equal('#00ff00')
			expect(store.cellStyle(1, SCORE).color).to.equal(undefined)
		})

		it('first-match stops at the first matching rule', () => {
			const store = useFormattingStore()
			store.ruleSets = [ruleSet([
				rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 }, { backgroundColor: '#ff0000' }),
				rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 1 }, { backgroundColor: '#00ff00', fontWeight: 'bold' }),
			], { mode: 'first-match' })]
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.rowStyle(1)).to.deep.include({ backgroundColor: '#ff0000' })
			expect(store.rowStyle(1).fontWeight).to.equal(undefined)
		})

		it('all-matches merges every matching rule, later rules winning', () => {
			const store = useFormattingStore()
			store.ruleSets = [ruleSet([
				rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 }, { backgroundColor: '#ff0000' }),
				rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 1 }, { backgroundColor: '#00ff00', fontWeight: 'bold' }),
			], { mode: 'all-matches' })]
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.rowStyle(1)).to.deep.include({ backgroundColor: '#00ff00', fontWeight: '700' })
		})

		it('skips disabled and broken rule sets and rules', () => {
			const store = useFormattingStore()
			const match = { columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 }
			store.ruleSets = [
				ruleSet([rule(match)], { enabled: false }),
				ruleSet([rule(match)], { broken: true }),
				ruleSet([rule(match, { backgroundColor: '#ff0000' }, { enabled: false })]),
				ruleSet([rule(match, { backgroundColor: '#ff0000' }, { broken: true })]),
			]
			store.evaluate([row(1, { [SCORE]: 9 })], columns)

			expect(store.rowStyle(1)).to.deep.equal(toCSS({}))
		})

		it('drops the style of a row that stops matching', () => {
			const store = useFormattingStore()
			store.ruleSets = [ruleSet([rule({ columnId: SCORE, columnType: 'number', operator: 'is-greater-than', value: 5 })])]
			store.evaluate([row(1, { [SCORE]: 9 })], columns)
			expect(store.rowStyle(1).backgroundColor).to.equal('#ff0000')

			store.evaluate([row(1, { [SCORE]: 1 })], columns)
			expect(store.rowStyle(1).backgroundColor).to.equal(undefined)
		})
	})

	describe('loading a view', () => {
		it('keeps the rules it already holds when the same view is reloaded', () => {
			const store = useFormattingStore()
			store.viewId = 7
			store.ruleSets = [ruleSet([])]

			store.loadForView(7)

			expect(store.ruleSets).to.have.length(1)
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

		it('returns an empty object for a missing style', () => {
			expect(toCSS(null)).to.deep.equal({})
		})
	})
})
