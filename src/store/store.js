import Vue from 'vue'
import Vuex from 'vuex'
// import Vuex, { Store } from 'vuex'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import data from './data.js'

Vue.use(Vuex)

// eslint-disable-next-line import/no-named-as-default-member
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
		setTable(state, table) {
			const index = state.tables.findIndex(t => t.id === table.id)
			state.tables[index] = table
		},
	},
	actions: {
		async insertNewTable({ commit, state }, { data }) {
			try {
				const res = await axios.post(generateUrl('/apps/tables/table'), data)
				const tables = state.tables
				tables.push(res.data)
				commit('setTables', tables)
				return res.data.id
			} catch (e) {
				const res = e.response
				if (res.status === 401) {
					showError(t('tables', 'Could not insert table, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					showError(t('tables', 'Could not insert table, no permissions.'))
				} else if (res.status === 404) {
					showError(t('tables', 'Could not insert table, resource not found.'))
				} else {
					showError(t('tables', 'Could not insert table, unknown error.'))
				}
				console.error(e)
				return false
			}
		},
		async loadTablesFromBE({ commit }) {
			commit('setTablesLoading', true)
			try {
				const res = await axios.get(generateUrl('/apps/tables/table'))
				commit('setTables', res.data)
			} catch (e) {
				const res = e.response
				if (res.status === 401) {
					showError(t('tables', 'Could not load tables, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					showError(t('tables', 'Could not load tables, no permissions.'))
				} else if (res.status === 404) {
					showError(t('tables', 'Could not load tables, resource not found.'))
				} else {
					showError(t('tables', 'Could not load tables, unknown error.'))
				}
				console.error(e)
				showError(t('tables', 'Could not fetch tables'))
			}
			commit('setTablesLoading', false)
			return true
		},
		async updateTable({ state, commit, dispatch }, { id, data }) {
			try {
				const res = await axios.put(generateUrl('/apps/tables/table/' + id), data)
				const table = res.data
				const tables = state.tables
				const index = tables.findIndex(t => t.id === table.id)
				tables[index] = table
				commit('setTables', [...tables])
			} catch (e) {
				const res = e.response
				if (res.status === 401) {
					showError(t('tables', 'Could not update table, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					showError(t('tables', 'Could not update table, no permissions.'))
				} else if (res.status === 404) {
					showError(t('tables', 'Could not update table, resource not found.'))
				} else {
					showError(t('tables', 'Could not update table, unknown error.'))
				}
				console.error(e)
				return false
			}
			return true
		},
		async removeTable({ state, commit }, { tableId }) {
			try {
				await axios.delete(generateUrl('/apps/tables/table/' + tableId))
				const tables = state.tables
				const index = tables.findIndex(t => t.id === tableId)
				tables.splice(index, 1)
				commit('setTables', [...tables])
			} catch (e) {
				const res = e.response
				if (res.status === 401) {
					showError(t('tables', 'Could not remove table, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					showError(t('tables', 'Could not remove table, no permissions.'))
				} else if (res.status === 404) {
					showError(t('tables', 'Could not remove table, resource not found.'))
				} else {
					showError(t('tables', 'Could not remove table, unknown error.'))
				}
				console.error(e)
				return false
			}
			return true
		},
		increaseRowsCountForTable({ state, commit, getters }, { tableId }) {
			const table = getters.getTable(tableId)
			if (table.rowsCount) {
				table.rowsCount++
			} else {
				table.rowsCount = 1
			}
			commit('setTable', table)
		},
		decreaseRowsCountForTable({ state, commit, getters }, { tableId }) {
			const table = getters.getTable(tableId)
			if (table.rowsCount) {
				table.rowsCount--
			} else {
				table.rowsCount = 0
			}
			commit('setTable', table)
		},
		setTableHasShares({ state, commit, getters }, { tableId, hasSHares }) {
			const table = getters.getTable(tableId)
			table.hasShares = !!hasSHares
			commit('setTable', table)
		},
	},
})
