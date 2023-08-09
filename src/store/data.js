import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../shared/utils/displayError.js'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'
import { MetaColumns } from '../shared/components/ncTable/mixins/metaColumns.js'
import { showError } from '@nextcloud/dialogs'

export default {
	state: {
		loading: false,
		rows: [],
		columns: [],
		viewSetting: {},
	},

	mutations: {
		setColumns(state, columns) {
			state.columns = columns
		},
		setRows(state, rows) {
			state.rows = rows
		},
		setLoading(state, value) {
			state.loading = !!(value)
		},
		setViewSetting(state, viewSetting) {
			state.viewSetting = Object.assign({}, viewSetting)
		},
	},

	getters: {
		getColumnById: (state) => (id) => {
			return state.columns.filter(column => column.id === id)[0]
		},
	},

	actions: {

		removeSorting({ commit, state }, { columnId }) {
			const viewSetting = state.viewSetting
			viewSetting.sorting = null
			commit('setViewSetting', viewSetting)
		},

		setSorting({ commit, state }, { columnId, mode }) {
			// mode can be 'ASC' or 'DESC'
			if (mode !== 'ASC' && mode !== 'DESC') {
				return
			}

			const viewSetting = state.viewSetting
			viewSetting.sorting = [{
				columnId,
				mode,
			}]

			commit('setViewSetting', viewSetting)
		},

		addFilter({ commit, state }, { columnId, operator, value }) {
			const viewSetting = state.viewSetting

			if (!viewSetting.filter) {
				viewSetting.filter = []
			}

			viewSetting.filter.push({
				columnId,
				operator,
				value,
			})

			commit('setViewSetting', viewSetting)
		},

		deleteFilter({ commit, state }, { id }) {
			const index = state.viewSetting?.filter?.findIndex(item => item.columnId + item.operator.id + item.value === id)
			if (index !== -1) {
				const localViewSetting = { ...state.viewSetting }
				localViewSetting.filter.splice(index, 1)
				commit('setViewSetting', localViewSetting)
			}
		},

		unhideColumn({ commit, state }, { columnId }) {
			const viewSetting = state.viewSetting
			const index = viewSetting.hiddenColumns.indexOf(columnId)
			if (index > -1) {
				viewSetting.hiddenColumns.splice(index, 1)
			}
			commit('setViewSetting', viewSetting)
		},

		hideColumn({ commit, state }, { columnId }) {
			const viewSetting = state.viewSetting
			if (!viewSetting.hiddenColumns) {
				viewSetting.hiddenColumns = [columnId]
			} else {
				viewSetting.hiddenColumns.push(columnId)
			}
			commit('setViewSetting', viewSetting)
		},

		setSearchString({ commit, state }, { str }) {
			const viewSetting = state.viewSetting
			viewSetting.searchString = str
			commit('setViewSetting', viewSetting)
		},

		resetViewSetting({ commit }) {
			commit('setViewSetting', {})
		},

		// COLUMNS
		async getColumnsFromBE({ commit }, { tableId, viewId }) {
			commit('setLoading', true)
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
			commit('setLoading', false)
			return columns
		},
		async loadColumnsFromBE({ commit, dispatch }, { view, table }) {
			let allColumns = await dispatch('getColumnsFromBE', { tableId: table?.id, viewId: view?.id })
			if (view) {
				allColumns = allColumns.concat(MetaColumns.filter(col => view.columns.includes(col.id)))
				allColumns = allColumns.sort(function(a, b) {
					return view.columns.indexOf(a.id) - view.columns.indexOf(b.id)
				  })
			}
			commit('setColumns', allColumns)
			return true
		},
		async insertNewColumn({ commit, state }, { data }) {
			commit('setLoading', true)
			let res = null

			try {
				res = await axios.post(generateUrl('/apps/tables/column'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not insert column.'))
				return false
			}

			const columns = state.columns
			columns.push(parseCol(res.data))
			commit('setColumns', columns)

			commit('setLoading', false)
			return true
		},
		async updateColumn({ state, commit }, { id, data }) {
			data.selectionOptions = JSON.stringify(data.selectionOptions)
			let res = null

			try {
				res = await axios.put(generateUrl('/apps/tables/column/' + id), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not update column.'))
				return false
			}

			const col = res.data
			const columns = state.columns
			const index = columns.findIndex(c => c.id === col.id)
			columns[index] = parseCol(col)
			commit('setColumns', [...columns])

			return true
		},
		async removeColumn({ state, commit }, { id }) {
			try {
				await axios.delete(generateUrl('/apps/tables/column/' + id))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove column.'))
				return false
			}

			const columns = state.columns
			const index = columns.findIndex(c => c.id === id)
			columns.splice(index, 1)
			commit('setColumns', [...columns])

			return true
		},

		// ROWS
		async loadRowsFromBE({ commit }, { tableId, viewId }) {
			commit('setLoading', true)
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

			commit('setRows', res.data)

			commit('setLoading', false)
			return true
		},
		removeRows({ commit }) {
			commit('setRows', [])
		},
		async updateRow({ state, commit, dispatch }, { id, viewId, data }) {
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

			const row = res.data
			const rows = state.rows
			const index = rows.findIndex(r => r.id === row.id)
			rows[index] = row
			commit('setRows', [...rows])
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

			const row = res.data
			const rows = state.rows
			rows.push(row)
			commit('setRows', [...rows])
			return true
		},
		async removeRow({ state, commit, dispatch }, { rowId, viewId }) {
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

			const rows = state.rows
			const index = rows.findIndex(r => r.id === rowId)
			rows.splice(index, 1)
			commit('setRows', [...rows])

			return true
		},
	},

}
