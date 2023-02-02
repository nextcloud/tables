import Vue from 'vue'
import Vuex from 'vuex'
// import Vuex, { Store } from 'vuex'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import data from './data.js'

Vue.use(Vuex)

export default new Vuex.Store({
	modules: {
		data,
	},

	state: {
		tablesLoading: false,
		tables: [],
		activeTableId: null,
	},

	getters: {
		activeTable(state) {
			if (state.tables && state.tables.filter(item => item.id === state.activeTableId).length > 0) { return state.tables.filter(item => item.id === state.activeTableId)[0] }
			return null
		},
		getTable: (state) => (id) => {
			return state.tables.filter(table => table.id === id)[0]
		},
	},
	mutations: {
		setTablesLoading(state, value) {
			state.tablesLoading = !!(value)
		},
		setActiveTableId(state, tableId) {
			if (state.activeTableId !== tableId) {
				state.activeTableId = tableId
			}
		},
		setTables(state, tables) {
			state.tables = tables
		},
	},
	actions: {
		async insertNewTable({ commit, state }, { data }) {
			try {
				const res = await axios.post(generateUrl('/apps/tables/table'), data)
				if (res.status === 200) {
					const tables = state.tables
					tables.push(res.data)
					commit('setTables', tables)
					return res.data.id
				} else {
					console.debug('axios error', res)
					return false
				}
			} catch (e) {
				console.error(e)
				return false
			}
		},
		async loadTablesFromBE({ commit }) {
			commit('setTablesLoading', true)
			try {
				const res = await axios.get(generateUrl('/apps/tables/table'))
				if (res.status === 200) {
					commit('setTables', res.data)
				} else {
					console.debug('axios error', res)
					return false
				}
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not fetch tables'))
			}
			commit('setTablesLoading', false)
			return true
		},
		async updateTable({ state, commit, dispatch }, { id, data }) {
			try {
				const res = await axios.put(generateUrl('/apps/tables/table/' + id), data)
				if (res.status === 200) {
					const table = res.data
					const tables = state.tables
					const index = tables.findIndex(t => t.id === table.id)
					tables[index] = table
					commit('setTables', [...tables])
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
		async removeTable({ state, commit }, { tableId }) {
			try {
				const res = await axios.delete(generateUrl('/apps/tables/table/' + tableId))
				if (res.status === 200) {
					const tables = state.tables
					const index = tables.findIndex(t => t.id === tableId)
					tables.splice(index, 1)
					commit('setTables', [...tables])
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
})
