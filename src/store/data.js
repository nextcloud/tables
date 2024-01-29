import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../shared/utils/displayError.js'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'
import { MetaColumns } from '../shared/components/ncTable/mixins/metaColumns.js'
import { showError } from '@nextcloud/dialogs'
import { set } from 'vue'

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
		removeColumns(state, { stateId }) {
			delete state.columns[stateId]
		},
		removeRows(state, { stateId }) {
			delete state.rows[stateId]
		},
		removeLoading(state, { stateId }) {
			delete state.loading[stateId]
		},

	},

	getters: {
		getColumnById: (state) => (tableId, columnId) => {
			return state.columns[tableId].filter(column => column.id === columnId)[0]
		},
	},

	actions: {
		initState({ commit }, { stateId }) {
			commit('setLoading', { stateId, value: null })
			commit('setColumns', { stateId, columns: [] })
			commit('setRows', { stateId, rows: [] })
		},
		removeDataFromState({ commit }, { stateId }) {
			commit('removeLoading', { stateId })
			commit('removeColumns', { stateId })
			commit('removeRows', { stateId })
		},
		// COLUMNS
		async getColumnsFromBE({ commit }, { tableId, viewId }) {
			const stateId = viewId ? 'view-' + viewId : tableId
			if (stateId) {
				commit('setLoading', { stateId, value: true })
			}
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
			if (stateId) {
				commit('setLoading', { stateId, value: false })
			}
			return columns
		},
		async loadColumnsFromBE({ commit, dispatch }, { view, tableId }) {
			let allColumns = await dispatch('getColumnsFromBE', { tableId: tableId, viewId: view?.id })
			if (view) {
				allColumns = allColumns.concat(MetaColumns.filter(col => view.columns.includes(col.id)))
				allColumns = allColumns.sort(function(a, b) {
					return view.columns.indexOf(a.id) - view.columns.indexOf(b.id)
				  })
			}
			const stateId = view?.id ? 'view-' + view.id : tableId
			if (stateId) {
				commit('setColumns', { stateId, columns: allColumns })
			}
			return true
		},
		async insertNewColumn({ commit, state }, { stateId, data }) {
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
		async updateColumn({ state, commit }, { id, stateId, data }) {
			data.selectionOptions = JSON.stringify(data.selectionOptions)
			let res = null

			try {
				res = await axios.put(generateUrl('/apps/tables/column/' + id), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not update column.'))
				return false
			}

			if (stateId) {
				const col = res.data
				const columns = state.columns[stateId]
				const index = columns.findIndex(c => c.id === col.id)
				columns[index] = parseCol(col)
				commit('setColumns', { stateId, columns: [...columns] })
			}

			return true
		},
		async removeColumn({ state, commit }, { id, stateId }) {
			try {
				await axios.delete(generateUrl('/apps/tables/column/' + id))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove column.'))
				return false
			}

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
			const stateId = viewId ? 'view-' + viewId : tableId
			if (stateId) {
				commit('setLoading', { stateId, value: true })
			}
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

			if (stateId) {
				commit('setRows', { stateId, rows: res.data })
				commit('setLoading', { stateId, value: false })
			}
			return true
		},
		removeRows({ commit }, { stateId }) {
			if (stateId) {
				commit('setRows', { stateId, rows: [] })
			}
		},
		async updateRow({ state, commit, dispatch }, { id, viewId, stateId, data }) {
			let res = null

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
			const stateId = viewId ? 'view-' + viewId : tableId
			let res = null

			try {
				res = await axios.post(generateUrl('/apps/tables/row'), { viewId, tableId, data })
			} catch (e) {
				displayError(e, t('tables', 'Could not insert row.'))
				return false
			}

			if (stateId) {
				const row = res.data
				const rows = state.rows[stateId]
				rows.push(row)
				commit('setRows', { stateId, rows: [...rows] })
			}
			return true
		},
		async removeRow({ state, commit, dispatch }, { rowId, viewId, stateId }) {
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
