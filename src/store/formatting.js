/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../shared/utils/displayError.js'
import { getFilterWithId } from '../shared/components/ncTable/mixins/filter.js'
import { resolveMagicValues } from '../shared/components/ncTable/mixins/magicFields.js'
import { MetaColumns, getCellForColumn } from '../shared/components/ncTable/mixins/metaColumns.js'
import { useTablesStore } from './store.js'

/**
 * @param {Array} columns parsed column instances of the view
 * @return {object} the columns keyed by their id
 */
function indexColumns(columns) {
	const index = {}
	for (const column of MetaColumns) {
		index[column.id] = column
	}
	for (const column of columns ?? []) {
		index[column.id] = column
	}
	return index
}

/**
 * @param {object} condition stored condition with columnId, operator and value
 * @param {object} columnIndex columns keyed by id
 * @return {object|null} the column and resolved filter, or null when the condition does not
 *   resolve against this view
 */
function compileCondition(condition, columnIndex) {
	const column = columnIndex[condition.columnId]
	const operator = getFilterWithId(condition.operator)
	if (!column || !operator) {
		return null
	}

	const filter = {
		columnId: condition.columnId,
		operator,
		value: condition.value ?? '',
	}
	resolveMagicValues(filter)

	return { column, filter }
}

/**
 * @param {object|null} compiled the output of compileCondition, or null when it did not resolve
 * @param {object} row row to test
 * @param {Map} cellCache this row's cells keyed by column id, so the per-condition scan of
 *   row.data happens once per column instead of once per condition
 * @return {boolean}
 */
function matchesCompiledCondition(compiled, row, cellCache) {
	if (compiled === null) {
		return false
	}

	if (!cellCache.has(compiled.column.id)) {
		cellCache.set(compiled.column.id, getCellForColumn(row, compiled.column))
	}
	const cell = { ...cellCache.get(compiled.column.id) }
	try {
		return compiled.column.isFilterFound(cell, compiled.filter) === true
	} catch (error) {
		return false
	}
}

/**
 * @param {Array} compiledGroups groups of compiled conditions, combined as OR of ANDs
 * @param {object} row row to test
 * @param {Map} cellCache this row's resolved cells
 * @return {boolean}
 */
function matchesCompiledGroups(compiledGroups, row, cellCache) {
	return compiledGroups.some(conditions =>
		conditions.length > 0
		&& conditions.every(condition => matchesCompiledCondition(condition, row, cellCache)),
	)
}

/**
 * @param {object} style stored style of a rule
 * @return {object}
 */
export function toCSS(style) {
	const css = {}
	if (!style) {
		return css
	}
	if (style.backgroundColor) {
		css.backgroundColor = style.backgroundColor
	}
	if (style.textColor) {
		css.color = style.textColor
	}
	if (style.fontWeight === 'bold') {
		css.fontWeight = '700'
	}
	if (style.fontStyle === 'italic') {
		css.fontStyle = 'italic'
	}
	if (style.textDecoration === 'strikethrough') {
		css.textDecoration = 'line-through'
	} else if (style.textDecoration === 'underline') {
		css.textDecoration = 'underline'
	}
	return css
}

const NO_STYLE = Object.freeze({})

const NO_MARKERS = Object.freeze([])

/**
 * @param {Array} rows rows currently loaded in the view
 * @param {Array} ruleSets rule sets of the view
 * @param {Array} columns parsed column instances of the view
 * @return {object} rowId to target key ('*' for the row) to ready-to-apply CSS
 */
function computeFmtMap(rows, ruleSets, columns) {
	const columnIndex = indexColumns(columns)
	const activeSets = ruleSets
		.filter(ruleSet => ruleSet.enabled && !ruleSet.broken)
		.sort((a, b) => a.sortOrder - b.sortOrder)
		.map(ruleSet => ({
			mode: ruleSet.mode,
			target: ruleSet.targetType === 'row' ? '*' : String(ruleSet.targetCol),
			marker: ruleSet.marker ?? null,
			title: ruleSet.title ?? '',
			rules: (ruleSet.rules ?? [])
				.filter(rule => rule.enabled && !rule.broken)
				.map(rule => ({
					format: rule.format,
					groups: (rule.condition?.groups ?? []).map(group =>
						(group.conditions ?? []).map(condition => compileCondition(condition, columnIndex)),
					),
				})),
		}))

	const fmtMap = {}
	for (const row of rows) {
		const styles = {}
		const markers = {}
		const cellCache = new Map()
		for (const ruleSet of activeSets) {
			let resolved = null
			for (const rule of ruleSet.rules) {
				if (!matchesCompiledGroups(rule.groups, row, cellCache)) {
					continue
				}
				resolved = ruleSet.mode === 'all-matches' ? { ...resolved, ...rule.format } : rule.format
				if (ruleSet.mode === 'first-match') {
					break
				}
			}
			if (!resolved) {
				continue
			}
			const target = ruleSet.target
			styles[target] = { ...resolved, ...(styles[target] ?? {}) }
			if (ruleSet.marker) {
				markers[target] ??= []
				markers[target].push({ marker: ruleSet.marker, title: ruleSet.title })
			}
		}

		const rowStyle = styles['*'] ?? {}
		const css = {}
		for (const [target, style] of Object.entries(styles)) {
			css[target] = toCSS(target === '*' ? style : { ...rowStyle, ...style })
		}
		fmtMap[row.id] = { css, markers }
	}
	return fmtMap
}

export const useFormattingStore = defineStore('formatting', {
	state: () => ({
		viewId: null,
		publicRuleSets: [],
		fmtMap: {},
		showFormattingManager: false,
	}),

	getters: {
		/**
		 * @param {object} state the store state
		 * @return {Array} the rule sets of the view currently being shown
		 */
		ruleSets: (state) => {
			if (state.viewId === null) {
				return state.publicRuleSets
			}
			return useTablesStore().getView(state.viewId)?.formatting ?? []
		},

		/**
		 * @param {object} state the store state
		 * @return {(viewId: number|null) => boolean} a predicate over the view id being
		 *   rendered, or null for a table
		 */
		appliesTo: (state) => (viewId) => state.viewId === (viewId ?? null),

		cellStyle: (state) => (rowId, columnId) => {
			const styles = state.fmtMap[rowId]?.css
			return styles?.[String(columnId)] ?? styles?.['*'] ?? NO_STYLE
		},

		rowStyle: (state) => (rowId) => state.fmtMap[rowId]?.css?.['*'] ?? NO_STYLE,

		/**
		 * @param {object} state the store state
		 * @return {(rowId: number, columnId: number, carriesRowMarkers: boolean) => Array}
		 *   the markers for that cell, each with its rule set's title
		 */
		cellMarkers: (state) => (rowId, columnId, carriesRowMarkers = false) => {
			const markers = state.fmtMap[rowId]?.markers
			if (!markers) {
				return NO_MARKERS
			}
			const own = markers[String(columnId)] ?? []
			const rowMarkers = carriesRowMarkers ? (markers['*'] ?? []) : []
			if (own.length === 0 && rowMarkers.length === 0) {
				return NO_MARKERS
			}

			return [...rowMarkers, ...own]
		},
	},

	actions: {
		loadForView(viewId) {
			this.viewId = viewId
			this.publicRuleSets = []
			this.fmtMap = {}
		},

		/**
		 * @param {Array} ruleSets rule sets as delivered in the public node data
		 */
		loadForPublicView(ruleSets) {
			this.viewId = null
			this.publicRuleSets = (ruleSets ?? []).slice()
			this.fmtMap = {}
		},

		clear() {
			this.viewId = null
			this.publicRuleSets = []
			this.fmtMap = {}
			this.showFormattingManager = false
		},

		/**
		 * @param {Array} ruleSets the view's rule sets
		 */
		applyRuleSets(ruleSets) {
			if (this.viewId === null) {
				this.publicRuleSets = ruleSets
				return
			}
			const view = useTablesStore().getView(this.viewId)
			if (view) {
				view.formatting = ruleSets
			}
		},

		evaluate(rows, columns) {
			this.fmtMap = computeFmtMap(rows, this.ruleSets, columns)
		},

		async createRuleSet(viewId, data) {
			try {
				const res = await axios.post(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets'),
					data,
				)
				this.applyRuleSets([...this.ruleSets, res.data])
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not create rule set.'))
				return null
			}
		},

		async updateRuleSet(viewId, id, data) {
			try {
				const res = await axios.put(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets/' + id),
					data,
				)
				const index = this.ruleSets.findIndex(ruleSet => ruleSet.id === id)
				if (index !== -1) {
					const next = this.ruleSets.slice()
					next[index] = res.data
					this.applyRuleSets(next)
				}
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not update rule set.'))
				return null
			}
		},

		async deleteRuleSet(viewId, id) {
			try {
				await axios.delete(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets/' + id),
				)
				this.applyRuleSets(this.ruleSets.filter(ruleSet => ruleSet.id !== id))
				return true
			} catch (e) {
				displayError(e, t('tables', 'Could not delete rule set.'))
				return false
			}
		},

		async reorder(viewId, orderedIds) {
			const previousOrder = this.ruleSets
			this.applyRuleSets(orderedIds
				.map((id, sortOrder) => {
					const ruleSet = previousOrder.find(candidate => candidate.id === id)
					return ruleSet ? { ...ruleSet, sortOrder } : null
				})
				.filter(Boolean))

			try {
				await axios.put(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/reorder'),
					{ orderedIds },
				)
			} catch (e) {
				this.applyRuleSets(previousOrder)
				displayError(e, t('tables', 'Could not reorder rule sets.'))
			}
		},

		async createRule(viewId, ruleSetId, data) {
			try {
				const { format, ...rest } = data
				const res = await axios.post(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets/' + ruleSetId + '/rules'),
					{ ...rest, style: format },
				)
				const ruleSet = this.ruleSets.find(candidate => candidate.id === ruleSetId)
				if (ruleSet) {
					ruleSet.rules.push(res.data)
				}
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not create rule.'))
				return null
			}
		},

		async updateRule(viewId, ruleSetId, id, data) {
			try {
				const { format, ...rest } = data
				const res = await axios.put(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets/' + ruleSetId + '/rules/' + id),
					{ ...rest, style: format },
				)
				const ruleSet = this.ruleSets.find(candidate => candidate.id === ruleSetId)
				if (ruleSet) {
					const index = ruleSet.rules.findIndex(rule => rule.id === id)
					if (index !== -1) {
						ruleSet.rules[index] = res.data
					}
				}
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not update rule.'))
				return null
			}
		},

		async deleteRule(viewId, ruleSetId, id) {
			try {
				await axios.delete(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets/' + ruleSetId + '/rules/' + id),
				)
				const ruleSet = this.ruleSets.find(candidate => candidate.id === ruleSetId)
				if (ruleSet) {
					ruleSet.rules = ruleSet.rules.filter(rule => rule.id !== id)
				}
				return true
			} catch (e) {
				displayError(e, t('tables', 'Could not delete rule.'))
				return false
			}
		},
	},
})
