/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../shared/utils/displayError.js'
import { useTablesStore } from './store.js'

// ── Evaluation helpers ────────────────────────────────────────────────────────

function selectionId(v) {
	return parseInt(String(v).replace('@selection-id-', ''))
}

function sameDay(val, ref) {
	const d = new Date(val)
	return d.getFullYear() === ref.getFullYear()
		&& d.getMonth() === ref.getMonth()
		&& d.getDate() === ref.getDate()
}

function sameWeek(val, ref) {
	const d = new Date(val)
	const mon = new Date(ref)
	mon.setDate(ref.getDate() - ((ref.getDay() + 6) % 7))
	mon.setHours(0, 0, 0, 0)
	const sun = new Date(mon)
	sun.setDate(mon.getDate() + 7)
	return d >= mon && d < sun
}

function getCellValue(row, columnId) {
	return row.data?.find(item => item.columnId === columnId)?.value ?? null
}

function evalCondition(cond, row) {
	const cellVal = getCellValue(row, cond.columnId)
	switch (cond.operator) {
	case 'isEmpty': return cellVal === null || cellVal === '' || cellVal === undefined
	case 'isNotEmpty': return cellVal !== null && cellVal !== '' && cellVal !== undefined
	case 'isTrue': return cellVal === true || cellVal === 1 || cellVal === '1'
	case 'isFalse': return cellVal === false || cellVal === 0 || cellVal === '0'
	case 'isToday': return sameDay(cellVal, new Date())
	case 'isThisWeek': return sameWeek(cellVal, new Date())
	case 'eq':
		if (cond.columnType === 'selection') return Number(cellVal) === selectionId(cond.value)
		return String(cellVal) === String(cond.value)
	case 'neq':
		if (cond.columnType === 'selection') return Number(cellVal) !== selectionId(cond.value)
		return String(cellVal) !== String(cond.value)
	case 'gt': return Number(cellVal) > Number(cond.value)
	case 'lt': return Number(cellVal) < Number(cond.value)
	case 'gte': return Number(cellVal) >= Number(cond.value)
	case 'lte': return Number(cellVal) <= Number(cond.value)
	case 'between': return Number(cellVal) >= Number(cond.values[0]) && Number(cellVal) <= Number(cond.values[1])
	case 'contains': return String(cellVal).toLowerCase().includes(String(cond.value).toLowerCase())
	case 'startsWith': return String(cellVal).toLowerCase().startsWith(String(cond.value).toLowerCase())
	case 'before': return new Date(cellVal) < new Date(cond.value)
	case 'after': return new Date(cellVal) > new Date(cond.value)
	case 'in':
		if (cond.columnType === 'selection') return cond.values.some(v => Number(cellVal) === selectionId(v))
		return cond.values.map(String).includes(String(cellVal))
	default: return false
	}
}

function evalConditionGroup(group, row) {
	return group.conditions.every(c => evalCondition(c, row))
}

function evalConditionSet(conditionSet, row) {
	return conditionSet.groups.some(group => evalConditionGroup(group, row))
}

export function toCSS(fmt) {
	if (!fmt) return {}
	return {
		backgroundColor: fmt.backgroundColor || undefined,
		color: fmt.textColor || undefined,
		fontWeight: fmt.fontWeight === 'bold' ? '700' : undefined,
		fontStyle: fmt.fontStyle === 'italic' ? 'italic' : undefined,
		textDecoration: fmt.textDecoration === 'strikethrough'
			? 'line-through'
			: fmt.textDecoration === 'underline'
				? 'underline'
				: undefined,
	}
}

function computeFmtMap(rows, ruleSets) {
	const fmtMap = {}
	const activeSets = [...ruleSets]
		.filter(rs => rs.enabled && !rs.broken)
		.sort((a, b) => a.sortOrder - b.sortOrder)

	for (const row of rows) {
		fmtMap[row.id] = {}
		for (const rs of activeSets) {
			let resolved = null
			for (const rule of rs.rules.filter(r => r.enabled && !r.broken)) {
				if (evalConditionSet(rule.condition, row)) {
					resolved = rs.mode === 'all-matches'
						? { ...resolved, ...rule.format }
						: rule.format
					if (rs.mode === 'first-match') break
				}
			}
			if (!resolved) continue
			const key = rs.targetType === 'row' ? '*' : String(rs.targetCol)
			fmtMap[row.id][key] = { ...(fmtMap[row.id][key] ?? {}), ...resolved }
		}
	}
	return fmtMap
}

// ── Store ─────────────────────────────────────────────────────────────────────

export const useFormattingStore = defineStore('formatting', {
	state: () => ({
		ruleSets: [],
		fmtMap: {},
		loading: false,
		showFormattingManager: false,
	}),

	getters: {
		hasRulesForColumn: (state) => (columnId) => {
			return state.ruleSets.some(rs =>
				rs.enabled && !rs.broken
				&& ((rs.targetType === 'column' && rs.targetCol === columnId)
					|| rs.targetType === 'row'),
			)
		},

		cellStyle: (state) => (rowId, columnId) => {
			const m = state.fmtMap[rowId] ?? {}
			return toCSS({ ...(m['*'] ?? {}), ...(m[String(columnId)] ?? {}) })
		},

		rowStyle: (state) => (rowId) => {
			const m = state.fmtMap[rowId] ?? {}
			return toCSS(m['*'] ?? {})
		},
	},

	actions: {
		loadForView(viewId) {
			const tablesStore = useTablesStore()
			const view = tablesStore.getView(viewId)
			this.ruleSets = (view?.formatting ?? []).slice()
			this.fmtMap = {}
		},

		evaluate(rows) {
			this.fmtMap = computeFmtMap(rows, this.ruleSets)
		},

		handleColumnDeleted(columnId) {
			this.ruleSets = this.ruleSets.map(rs => ({
				...rs,
				rules: rs.rules.map(rule => {
					const refs = rule.condition?.groups?.flatMap(g => g.conditions.map(c => c.columnId)) ?? []
					if (refs.includes(columnId)) {
						return { ...rule, broken: true, enabled: false }
					}
					return rule
				}),
			}))
		},

		handleColumnTypeChanged(columnId, newType) {
			this.ruleSets = this.ruleSets.map(rs => ({
				...rs,
				rules: rs.rules.map(rule => {
					const mismatch = rule.condition?.groups?.some(g =>
						g.conditions.some(c => c.columnId === columnId && c.columnType !== newType),
					) ?? false
					if (mismatch) {
						return { ...rule, broken: true, enabled: false }
					}
					return rule
				}),
			}))
		},

		async createRuleSet(viewId, data) {
			this.loading = true
			try {
				const res = await axios.post(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets'),
					data,
				)
				this.ruleSets.push(res.data)
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not create rule set.'))
				return null
			} finally {
				this.loading = false
			}
		},

		async updateRuleSet(viewId, id, data) {
			this.loading = true
			try {
				const res = await axios.put(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets/' + id),
					data,
				)
				const idx = this.ruleSets.findIndex(rs => rs.id === id)
				if (idx !== -1) this.ruleSets.splice(idx, 1, res.data)
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not update rule set.'))
				return null
			} finally {
				this.loading = false
			}
		},

		async deleteRuleSet(viewId, id) {
			this.loading = true
			try {
				await axios.delete(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets/' + id),
				)
				this.ruleSets = this.ruleSets.filter(rs => rs.id !== id)
				return true
			} catch (e) {
				displayError(e, t('tables', 'Could not delete rule set.'))
				return false
			} finally {
				this.loading = false
			}
		},

		async reorder(viewId, orderedIds) {
			// Apply locally immediately — sortOrder = position in submitted list
			this.ruleSets = orderedIds
				.map((id, idx) => {
					const rs = this.ruleSets.find(r => r.id === id)
					return rs ? { ...rs, sortOrder: idx } : null
				})
				.filter(Boolean)

			try {
				await axios.put(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/reorder'),
					{ orderedIds },
				)
			} catch (e) {
				displayError(e, t('tables', 'Could not reorder rule sets.'))
			}
		},

		async createRule(viewId, ruleSetId, data) {
			this.loading = true
			try {
				const res = await axios.post(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets/' + ruleSetId + '/rules'),
					data,
				)
				const rs = this.ruleSets.find(r => r.id === ruleSetId)
				if (rs) rs.rules.push(res.data)
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not create rule.'))
				return null
			} finally {
				this.loading = false
			}
		},

		async updateRule(viewId, ruleSetId, id, data) {
			this.loading = true
			try {
				const res = await axios.put(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets/' + ruleSetId + '/rules/' + id),
					data,
				)
				const rs = this.ruleSets.find(r => r.id === ruleSetId)
				if (rs) {
					const idx = rs.rules.findIndex(r => r.id === id)
					if (idx !== -1) rs.rules.splice(idx, 1, res.data)
				}
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not update rule.'))
				return null
			} finally {
				this.loading = false
			}
		},

		async deleteRule(viewId, ruleSetId, id) {
			this.loading = true
			try {
				await axios.delete(
					generateUrl('/apps/tables/api/1/views/' + viewId + '/formatting/rulesets/' + ruleSetId + '/rules/' + id),
				)
				const rs = this.ruleSets.find(r => r.id === ruleSetId)
				if (rs) rs.rules = rs.rules.filter(r => r.id !== id)
				return true
			} catch (e) {
				displayError(e, t('tables', 'Could not delete rule.'))
				return false
			} finally {
				this.loading = false
			}
		},
	},
})
