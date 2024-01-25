import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../shared/utils/displayError.js'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'
import { MetaColumns } from '../shared/components/ncTable/mixins/metaColumns.js'
import { showError } from '@nextcloud/dialogs'
import Vue from 'vue'

export default {
	state: {
		loading: {},
		rows: {},
		columns: {},
	},

	mutations: {
		setColumns(state, {tableId, columns}) {
			Vue.set(state.columns, tableId, columns)
		},
		setRows(state, {tableId, rows}) {
			Vue.set(state.rows, tableId, rows)
		},
		setLoading(state, {tableId, value}) {
			Vue.set(state.loading, tableId, !!(value))
		},
		removeColumns(state, {tableId}) {
			delete state.columns[tableId]
		},
		removeRows(state, {tableId}) {
			delete state.rows[tableId]
		},
		removeLoading(state, {tableId}) {
			delete state.loading[tableId]
		},
		
	},

	getters: {
		getColumnById: (state) => (tableId, id) => {
			return state.columns[tableId].filter(column => column.id === id)[0]
		},
	},

	actions: {
		initialize({commit}, {tableId}) {
			commit('setLoading', {tableId, value: null})
			commit('setColumns', {tableId, columns: []})
			commit('setRows', {tableId, rows: []})
		},
		removeData({commit}, {tableId}) {
			commit('removeLoading', {tableId})
			commit('removeColumns', {tableId})
			commit('removeRows', {tableId})
		},
		// COLUMNS
		async getColumnsFromBE({ commit }, { tableId, viewId }) {
			commit('setLoading', {tableId, value:true})
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
			commit('setLoading', {tableId, value:false})
			return columns
		},
		async loadColumnsFromBE({ commit, dispatch }, { view, table, tableId }) {
			let allColumns = await dispatch('getColumnsFromBE', { tableId: tableId, viewId: view?.id })
			if (view) {
				allColumns = allColumns.concat(MetaColumns.filter(col => view.columns.includes(col.id)))
				allColumns = allColumns.sort(function(a, b) {
					return view.columns.indexOf(a.id) - view.columns.indexOf(b.id)
				  })
			}
			if (tableId) {
				commit('setColumns', {tableId, columns: allColumns})
			}
			return true
		},
		async insertNewColumn({ commit, state }, { tableId, data }) {
			commit('setLoading', {tableId, value: true})
			let res = null

			try {
				res = await axios.post(generateUrl('/apps/tables/column'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not insert column.'))
				return false
			}

			const columns = state.columns[tableId]
			columns.push(parseCol(res.data))
			
			commit('setColumns', {tableId, columns})

			commit('setLoading', {tableId, value: false})
			return true
		},
		async updateColumn({ state, commit }, { id, tableId, data }) {
			data.selectionOptions = JSON.stringify(data.selectionOptions)
			let res = null

			try {
				res = await axios.put(generateUrl('/apps/tables/column/' + id), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not update column.'))
				return false
			}

			const col = res.data
			const columns = state.columns[tableId]
			const index = columns.findIndex(c => c.id === col.id)
			columns[index] = parseCol(col)
			commit('setColumns', {tableId, columns: [...columns]})

			return true
		},
		async removeColumn({ state, commit }, { tableId, id }) {
			try {
				await axios.delete(generateUrl('/apps/tables/column/' + id))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove column.'))
				return false
			}

			if (tableId) {
				const columns = state.columns[tableId]
				const index = columns.findIndex(c => c.id === id)
				columns.splice(index, 1)
				commit('setColumns', {tableId, columns: [...columns]})
			}

			return true
		},

		// ROWS
		async loadRowsFromBE({ commit }, { tableId, viewId }) {
			commit('setLoading', {tableId, value: true})
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

			if (tableId) {
				commit('setRows', {tableId, rows: res.data})
				commit('setLoading', {tableId, value: false})
			}
			return true
		},
		removeRows({ commit }, {tableId}) {
			if (tableId) {
				commit('setRows', {tableId, rows:[]})
			}
		},
		async updateRow({ state, commit, dispatch }, { id, tableId, viewId, data }) {
			let res = null

			try {
				res = await axios.put(generateUrl('/apps/tables/row/' + id), { tableId, viewId, data })
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

			if (tableId) {
				const row = res.data
				const rows = state.rows[tableId]
				const index = rows.findIndex(r => r.id === row.id)
				rows[index] = row
				commit('setRows', {tableId, rows:[...rows]})
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
			if (viewId) {
				tableId = res?.tableId
			}
			if (tableId) {
				const row = res.data
				const rows = state.rows[tableId]
				rows.push(row)
				commit('setRows', {tableId, rows:[...rows]})
			}
			return true
		},
		async removeRow({ state, commit, dispatch }, { rowId, viewId, tableId }) {
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

			const id =  viewId || tableId
			if (id) {
				const rows = state.rows[tableId]
				const index = rows.findIndex(r => r.id === rowId)
				rows.splice(index, 1)
				commit('setRows', {tableId, rows: [...rows]})
			}
			return true
		},
	},

}
