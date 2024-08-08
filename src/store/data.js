/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../shared/utils/displayError.js'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'
import { MetaColumns } from '../shared/components/ncTable/mixins/metaColumns.js'
import { showError } from '@nextcloud/dialogs'
import { set } from 'vue'

function genStateKey(isView, elementId) {
	elementId = elementId.toString()
	return isView ? 'view-' + elementId : elementId
}

export default {
	state: {
		loading: {},
		rows: {},
		columns: {},
	},

	mutations: {
		setColumns(state, { stateId, columns }) {
			set(state.columns, stateId, columns)
		},
		setRows(state, { stateId, rows }) {
			set(state.rows, stateId, rows)
		},
		setLoading(state, { stateId, value }) {
			set(state.loading, stateId, !!(value))
		},
		clearColumns(state) {
			state.columns = {}
		},
		clearRows(state) {
			state.rows = {}
		},
		clearLoading(state) {
			state.loading = {}
		},

	},

	actions: {
		clearState({ commit }) {
			commit('clearLoading')
			commit('clearColumns')
			commit('clearRows')
		},
		// COLUMNS
		async getColumnsFromBE({ commit }, { tableId, viewId }) {
			const stateId = genStateKey(!!(viewId), viewId ?? tableId)
			commit('setLoading', { stateId, value: true })
			let res = null

			try {
				if (tableId && viewId) {
					// Get all table columns. Try to access from view (Test if you have read access for view to read table columns)
					res = await axios.get(generateUrl('/apps/tables/column/table/' + tableId + '/view/' + viewId))
				  } else if (tableId && !viewId) {
					// Get all table columns without view. Table manage rights needed
					res = await axios.get(generateUrl('/apps/tables/column/table/' + tableId))
				  } else if (!tableId && viewId) {
					// Get all view columns.
					res = await axios.get(generateUrl('/apps/tables/column/view/' + viewId))
				  }
				if (!Array.isArray(res.data)) {
					const e = new Error('Expected array, but is not')
					displayError(e, 'Format for loaded columns not valid.')
					return false
				}
			} catch (e) {
				displayError(e, t('tables', 'Could not load columns.'))
				return false
			}
			const columns = res.data.map(col => parseCol(col))
			commit('setLoading', { stateId, value: false })
			return columns
		},
		async loadColumnsFromBE({ commit, dispatch }, { view, tableId }) {
			let allColumns = await dispatch('getColumnsFromBE', { tableId, viewId: view?.id })
			if (view) {
				allColumns = allColumns.concat(MetaColumns.filter(col => view.columns.includes(col.id)))
				allColumns = allColumns.sort(function(a, b) {
					return view.columns.indexOf(a.id) - view.columns.indexOf(b.id)
				  })
			}
			const stateId = genStateKey(!!(view?.id), view?.id ?? tableId)
			commit('setColumns', { stateId, columns: allColumns })
			return true
		},
		async insertNewColumn({ commit, state }, { isView, elementId, data }) {
			const stateId = genStateKey(isView, elementId)
			commit('setLoading', { stateId, value: true })
			let res = null

			try {
				res = await axios.post(generateUrl('/apps/tables/column'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not insert column.'))
				return false
			}
			if (stateId) {
				const columns = state.columns[stateId]
				columns.push(parseCol(res.data))
				commit('setColumns', { stateId, columns })
				commit('setLoading', { stateId, value: false })
			}
			return true
		},
		async updateColumn({ state, commit }, { id, isView, elementId, data }) {
			data.selectionOptions = JSON.stringify(data.selectionOptions)
			data.usergroupDefault = JSON.stringify(data.usergroupDefault)
			let res = null

			try {
				res = await axios.put(generateUrl('/apps/tables/column/' + id), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not update column.'))
				return false
			}

			const stateId = genStateKey(isView, elementId)
			if (stateId) {
				const col = res.data
				const columns = state.columns[stateId]
				const index = columns.findIndex(c => c.id === col.id)
				columns[index] = parseCol(col)
				commit('setColumns', { stateId, columns: [...columns] })
			}

			return true
		},
		async removeColumn({ state, commit }, { id, isView, elementId }) {
			try {
				await axios.delete(generateUrl('/apps/tables/column/' + id))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove column.'))
				return false
			}

			const stateId = genStateKey(isView, elementId)
			if (stateId) {
				const columns = state.columns[stateId]
				const index = columns.findIndex(c => c.id === id)
				columns.splice(index, 1)
				commit('setColumns', { stateId, columns: [...columns] })
			}

			return true
		},

		// ROWS
		async loadRowsFromBE({ commit }, { tableId, viewId }) {
			const stateId = genStateKey(!!(viewId), viewId ?? tableId)
			commit('setLoading', { stateId, value: true })
			let res = null

			try {
				if (viewId) {
					res = await axios.get(generateUrl('/apps/tables/row/view/' + viewId))
				} else {
					res = await axios.get(generateUrl('/apps/tables/row/table/' + tableId))
				}
			} catch (e) {
				displayError(e, t('tables', 'Could not load rows.'))
				return false
			}

			commit('setRows', { stateId, rows: res.data })
			commit('setLoading', { stateId, value: false })
			return true
		},
		removeRows({ commit }, { isView, elementId }) {
			const stateId = genStateKey(isView, elementId)
			commit('setRows', { stateId, rows: [] })
		},
		async updateRow({ state, commit, dispatch }, { id, isView, elementId, data }) {
			let res = null
			const viewId = isView ? elementId : null

			try {
				res = await axios.put(generateUrl('/apps/tables/row/' + id), { viewId, data })
			} catch (e) {
				console.debug(e?.response)
				if (e?.response?.data?.message?.startsWith('User should not be able to access row')) {
					showError(t('tables', 'Outdated data. View is reloaded'))
					dispatch('loadRowsFromBE', { viewId })
				} else {
					displayError(e, t('tables', 'Could not update row.'))
				}
				return false
			}

			const stateId = genStateKey(isView, elementId)
			if (stateId) {
				const row = res.data
				const rows = state.rows[stateId]
				const index = rows.findIndex(r => r.id === row.id)
				rows[index] = row
				commit('setRows', { stateId, rows: [...rows] })
			}
			return true
		},
		async insertNewRow({ state, commit, dispatch }, { viewId, tableId, data }) {
			let res = null

			try {
				res = await axios.post(generateUrl('/apps/tables/row'), { viewId, tableId, data })
			} catch (e) {
				displayError(e, t('tables', 'Could not insert row.'))
				return false
			}

			const stateId = genStateKey(!!(viewId), viewId ?? tableId)
			if (stateId) {
				const row = res.data
				const rows = state.rows[stateId]
				rows.push(row)
				commit('setRows', { stateId, rows: [...rows] })
			}
			return true
		},
		async removeRow({ state, commit, dispatch }, { rowId, isView, elementId }) {
			const viewId = isView ? elementId : null
			try {
				if (viewId) await axios.delete(generateUrl('/apps/tables/view/' + viewId + '/row/' + rowId))
				else await axios.delete(generateUrl('/apps/tables/row/' + rowId))
			} catch (e) {
				if (e?.response?.data?.message?.startsWith('User should not be able to access row')) {
					showError(t('tables', 'Outdated data. View is reloaded'))
					dispatch('loadRowsFromBE', { viewId })
				} else {
					displayError(e, t('tables', 'Could not remove row.'))
				}
				return false
			}

			const stateId = genStateKey(isView, elementId)
			if (stateId) {
				const rows = state.rows[stateId]
				const index = rows.findIndex(r => r.id === rowId)
				rows.splice(index, 1)
				commit('setRows', { stateId, rows: [...rows] })
			}
			return true
		},
	},

}
