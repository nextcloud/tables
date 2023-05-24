import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../shared/utils/displayError.js'

export default {
	state: {
		loading: false,
		rows: [],
		columns: [],
		view: {},
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
		setView(state, view) {
			state.view = Object.assign({}, view)
		},
	},

	getters: {
		getColumnById: (state) => (id) => {
			return state.columns.filter(column => column.id === id)[0]
		},
		getDefaultValueFromColumn: (state) => (id) => {
			const column = this.getColumnById(id)
			return column[column.type + 'Default']
		},
	},

	actions: {

		addSorting({ commit, state }, { columnId, mode }) {
			// mode can be 'asc' or 'desc'
			if (mode !== 'asc' && mode !== 'desc') {
				return
			}

			const view = state.view

			const sorting = []
			sorting.push({
				columnId,
				mode,
			})

			view.sorting = sorting

			commit('setView', view)
		},

		addFilter({ commit, state }, { columnId, operator, value }) {
			const view = state.view

			if (!view.filter) {
				view.filter = []
			}

			view.filter.push({
				columnId,
				operator,
				value,
			})

			commit('setView', view)
		},

		deleteFilter({ commit, state }, { id }) {
			const index = state.view?.filter?.findIndex(item => item.columnId + item.operator + item.value === id)
			if (index !== -1) {
				const localView = { ...state.view }
				localView.filter.splice(index, 1)
				commit('setView', localView)
			}
		},

		setSearchString({ commit, state }, { str }) {
			const view = state.view
			view.searchString = str
			commit('setView', view)
		},

		// COLUMNS
		async loadColumnsFromBE({ commit }, { tableId }) {
			commit('setLoading', true)
			try {
				const res = await axios.get(generateUrl('/apps/tables/column/' + tableId))
				if (res.status === 200 && res.data && Array.isArray(res.data)) {
					const columns = res.data.sort((a, b) => {
						if (a.orderWeight < b.orderWeight) { return 1 }
						if (a.orderWeight > b.orderWeight) { return -1 }
						return 0
					})
					commit('setColumns', columns)
				} else {
					console.debug('data error: format not valid', res)
					return false
				}
			} catch (e) {
				displayError(e, t('tables', 'Could not load columns.'))
				return false
			}
			commit('setLoading', false)
			return true
		},
		async insertNewColumn({ commit, state }, { data }) {
			commit('setLoading', true)
			try {
				const res = await axios.post(generateUrl('/apps/tables/column'), data)
				const columns = state.columns
				columns.push(res.data)
				commit('setColumns', columns)
			} catch (e) {
				displayError(e, t('tables', 'Could not insert column.'))
				return false
			}
			commit('setLoading', false)
			return true
		},
		async updateColumn({ state, commit }, { id, data }) {
			data.selectionOptions = JSON.stringify(data.selectionOptions)
			try {
				const res = await axios.put(generateUrl('/apps/tables/column/' + id), data)
				const col = res.data
				const columns = state.columns
				const index = columns.findIndex(c => c.id === col.id)
				columns[index] = col
				commit('setColumns', [...columns])
			} catch (e) {
				displayError(e, t('tables', 'Could not update column.'))
				return false
			}
			return true
		},
		async removeColumn({ state, commit }, { id }) {
			try {
				await axios.delete(generateUrl('/apps/tables/column/' + id))
				const columns = state.columns
				const index = columns.findIndex(c => c.id === id)
				columns.splice(index, 1)
				commit('setColumns', [...columns])
			} catch (e) {
				displayError(e, t('tables', 'Could not remove column.'))
				return false
			}
			return true
		},

		// ROWS
		async loadRowsFromBE({ commit }, { tableId }) {
			commit('setLoading', true)
			try {
				const res = await axios.get(generateUrl('/apps/tables/row/' + tableId))
				commit('setRows', res.data)
			} catch (e) {
				displayError(e, t('tables', 'Could not load rows.'))
				return false
			}
			commit('setLoading', false)
			return true
		},
		async updateRow({ state, commit, dispatch }, { id, data }) {
			try {
				const res = await axios.put(generateUrl('/apps/tables/row/' + id), { data })
				const row = res.data
				const rows = state.rows
				const index = rows.findIndex(r => r.id === row.id)
				rows[index] = row
				commit('setRows', [...rows])
				return true
			} catch (e) {
				displayError(e, t('tables', 'Could not update row.'))
				return false
			}
		},
		async insertNewRow({ state, commit, dispatch }, { tableId, data }) {
			try {
				const res = await axios.post(generateUrl('/apps/tables/row'), { tableId, data })
				const row = res.data
				const rows = state.rows
				rows.push(row)
				commit('setRows', [...rows])
				dispatch('increaseRowsCountForTable', { tableId })
			} catch (e) {
				displayError(e, t('tables', 'Could not insert row.'))
				return false
			}
			return true
		},
		async removeRow({ state, commit, dispatch }, { rowId }) {
			try {
				const res = await axios.delete(generateUrl('/apps/tables/row/' + rowId))
				const rows = state.rows
				const index = rows.findIndex(r => r.id === rowId)
				rows.splice(index, 1)
				commit('setRows', [...rows])
				dispatch('decreaseRowsCountForTable', { tableId: res.data.tableId })
			} catch (e) {
				displayError(e, t('tables', 'Could not remove row.'))
				return false
			}
			return true
		},
	},

}
