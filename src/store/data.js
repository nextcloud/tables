import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	state: {
		loading: false,
		rows: [],
		columns: [],
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
					console.debug('axios error', res)
					return false
				}
			} catch (e) {
				console.error(e)
				return false
			}
			commit('setLoading', false)
			return true
		},
		async insertNewColumn({ commit, state }, { data }) {
			commit('setLoading', true)
			try {
				const res = await axios.post(generateUrl('/apps/tables/column'), data)
				if (res.status === 200) {
					const columns = state.columns
					columns.push(res.data)
					commit('setColumns', columns)
				} else {
					console.debug('axios error', res)
					return false
				}
			} catch (e) {
				console.error(e)
				return false
			}
			commit('setLoading', false)
			return true
		},
		async updateColumn({ state, commit }, { id, data }) {
			try {
				const res = await axios.put(generateUrl('/apps/tables/column/' + id), data)
				if (res.status === 200) {
					const col = res.data
					const columns = state.columns
					const index = columns.findIndex(c => c.id === col.id)
					columns[index] = col
					commit('setColumns', [...columns])
				} else {
					console.debug('axios error', res)
					return false
				}
			} catch (e) {
				console.error(e)
				return false
			}
			return true
		},
		async removeColumn({ state, commit }, { id }) {
			try {
				const res = await axios.delete(generateUrl('/apps/tables/column/' + id))
				if (res.status === 200) {
					const columns = state.columns
					const index = columns.findIndex(c => c.id === id)
					columns.splice(index, 1)
					commit('setColumns', [...columns])
				} else {
					console.debug('axios error', res)
					return false
				}
			} catch (e) {
				console.error(e)
				return false
			}
			return true
		},

		// ROWS
		async loadRowsFromBE({ commit }, { tableId }) {
			commit('setLoading', true)
			try {
				const res = await axios.get(generateUrl('/apps/tables/row/' + tableId))
				if (res.status === 200 && res.data) {
					commit('setRows', res.data)
				} else {
					console.debug('axios error', res)
					return false
				}
			} catch (e) {
				console.error(e)
				return false
			}
			commit('setLoading', false)
			return true
		},
		async updateRow({ state, commit, dispatch }, { id, data }) {
			try {
				const res = await axios.put(generateUrl('/apps/tables/row/' + id), { data })
				if (res.status === 200) {
					const row = res.data
					const rows = state.rows
					const index = rows.findIndex(r => r.id === row.id)
					rows[index] = row
					commit('setRows', [...rows])
				} else {
					console.debug('axios error', res)
					return false
				}
			} catch (e) {
				console.error(e)
				return false
			}
			return true
		},
		async insertNewRow({ state, commit, dispatch }, { tableId, data }) {
			try {
				const res = await axios.post(generateUrl('/apps/tables/row'), { tableId, data })
				if (res.status === 200) {
					const row = res.data
					const rows = state.rows
					rows.push(row)
					commit('setRows', [...rows])
				} else {
					console.debug('axios error', res)
					return false
				}
			} catch (e) {
				console.error(e)
				return false
			}
			return true
		},
		async removeRow({ state, commit }, { rowId }) {
			try {
				const res = await axios.delete(generateUrl('/apps/tables/row/' + rowId))
				if (res.status === 200) {
					const rows = state.rows
					const index = rows.findIndex(r => r.id === rowId)
					rows.splice(index, 1)
					commit('setRows', [...rows])
				} else {
					console.debug('axios error', res)
					return false
				}
			} catch (e) {
				console.error(e)
				return false
			}
			return true
		},
	},

}
