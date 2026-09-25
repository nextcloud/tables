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
import { useTablesStore } from './store.js'

/**
 * @param {Array} columns parsed column instances of the view
 * @return {object} the columns keyed by their id
 */
function indexColumns(columns) {
	const index = {}
	for (const column of columns ?? []) {
		index[column.id] = column
	}
	return index
}

/**
 * Decide whether one condition holds for a row.
 *
 * The match is delegated to the column type, which is the same code path the view filters
 * use, so a condition selects the rows its equivalent filter would select.
 *
 * @param {object} condition stored condition with columnId, operator and value
 * @param {object} row row as delivered by the data store
 * @param {object} columnIndex columns keyed by id
 * @return {boolean}
 */
function matchesCondition(condition, row, columnIndex) {
	const column = columnIndex[condition.columnId]
	const operator = getFilterWithId(condition.operator)
	if (!column || !operator) {
		return false
	}

	// isFilterFound marks the cell it is handed, so give it a throwaway copy
	const cell = {
		columnId: condition.columnId,
		value: row.data?.find(item => item.columnId === condition.columnId)?.value ?? null,
	}
	const filter = { columnId: condition.columnId, operator, value: condition.value ?? '' }
	resolveMagicValues(filter)

	try {
		return column.isFilterFound(cell, filter) === true
	} catch (error) {
		// the column type does not implement this operator, so nothing can match
		return false
	}
}

/**
 * @param {object} conditionSet groups of conditions, combined as OR of ANDs
 * @param {object} row row to test
 * @param {object} columnIndex columns keyed by id
 * @return {boolean}
 */
function matchesConditionSet(conditionSet, row, columnIndex) {
	return (conditionSet?.groups ?? []).some(group =>
		(group.conditions ?? []).length > 0
		&& group.conditions.every(condition => matchesCondition(condition, row, columnIndex)),
	)
}

/**
 * Translate a stored style into the CSS properties Vue applies to a row or a cell.
 *
 * @param {object} style stored style of a rule
 * @return {object}
 */
export function toCSS(style) {
	if (!style) {
		return {}
	}
	return {
		backgroundColor: style.backgroundColor || undefined,
		color: style.textColor || undefined,
		fontWeight: style.fontWeight === 'bold' ? '700' : undefined,
		fontStyle: style.fontStyle === 'italic' ? 'italic' : undefined,
		textDecoration: style.textDecoration === 'strikethrough'
			? 'line-through'
			: style.textDecoration === 'underline'
				? 'underline'
				: undefined,
	}
}

/**
 * Resolve the style of every row and cell in one pass.
 *
 * @param {Array} rows rows currently loaded in the view
 * @param {Array} ruleSets rule sets of the view
 * @param {Array} columns parsed column instances of the view
 * @return {object} rowId to target key ('*' for the row) to style
 */
function computeFmtMap(rows, ruleSets, columns) {
	const columnIndex = indexColumns(columns)
	const activeSets = [...ruleSets]
		.filter(ruleSet => ruleSet.enabled && !ruleSet.broken)
		.sort((a, b) => a.sortOrder - b.sortOrder)

	const fmtMap = {}
	for (const row of rows) {
		fmtMap[row.id] = {}
		for (const ruleSet of activeSets) {
			let resolved = null
			for (const rule of (ruleSet.rules ?? []).filter(r => r.enabled && !r.broken)) {
				if (!matchesConditionSet(rule.condition, row, columnIndex)) {
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
			const target = ruleSet.targetType === 'row' ? '*' : String(ruleSet.targetCol)
			fmtMap[row.id][target] = { ...(fmtMap[row.id][target] ?? {}), ...resolved }
		}
	}
	return fmtMap
}

export const useFormattingStore = defineStore('formatting', {
	state: () => ({
		viewId: null,
		ruleSets: [],
		fmtMap: {},
		showFormattingManager: false,
	}),

	getters: {
		cellStyle: (state) => (rowId, columnId) => {
			const styles = state.fmtMap[rowId] ?? {}
			return toCSS({ ...(styles['*'] ?? {}), ...(styles[String(columnId)] ?? {}) })
		},

		rowStyle: (state) => (rowId) => {
			const styles = state.fmtMap[rowId] ?? {}
			return toCSS(styles['*'] ?? {})
		},
	},

	actions: {
		loadForView(viewId) {
			// This store is the only writer of formatting rules in the client, so a reload of
			// the view it already holds must keep them: replacing them would reset the editors
			// and drop rules that are still being edited.
			if (this.viewId === viewId) {
				return
			}
			const view = useTablesStore().getView(viewId)
			this.viewId = viewId
			this.ruleSets = (view?.formatting ?? []).slice()
			this.fmtMap = {}
		},

		evaluate(rows, columns) {
			this.fmtMap = computeFmtMap(rows, this.ruleSets, columns)
		},

		/**
		 * Mirror the rules into the tables store, whose copy of the view is otherwise only
		 * refreshed on a full reload and would hand back outdated rules on the next visit.
		 */
		syncToTablesStore() {
			const view = useTablesStore().getView(this.viewId)
			if (view) {
				view.formatting = this.ruleSets.slice()
			}
		},

		async createRuleSet(viewId, data) {
			try {
				const res = await axios.post(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets'),
					data,
				)
				this.ruleSets.push(res.data)
				this.syncToTablesStore()
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
					this.ruleSets[index] = res.data
				}
				this.syncToTablesStore()
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
				this.ruleSets = this.ruleSets.filter(ruleSet => ruleSet.id !== id)
				this.syncToTablesStore()
				return true
			} catch (e) {
				displayError(e, t('tables', 'Could not delete rule set.'))
				return false
			}
		},

		async reorder(viewId, orderedIds) {
			const previousOrder = this.ruleSets
			this.ruleSets = orderedIds
				.map((id, sortOrder) => {
					const ruleSet = this.ruleSets.find(candidate => candidate.id === id)
					return ruleSet ? { ...ruleSet, sortOrder } : null
				})
				.filter(Boolean)

			try {
				await axios.put(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/reorder'),
					{ orderedIds },
				)
				this.syncToTablesStore()
			} catch (e) {
				this.ruleSets = previousOrder
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
				this.syncToTablesStore()
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
				this.syncToTablesStore()
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
				this.syncToTablesStore()
				return true
			} catch (e) {
				displayError(e, t('tables', 'Could not delete rule.'))
				return false
			}
		},
	},
})
