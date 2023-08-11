import Vue from 'vue'
import Vuex from 'vuex'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import data from './data.js'
import displayError from '../shared/utils/displayError.js'

Vue.use(Vuex)

// eslint-disable-next-line import/no-named-as-default-member
export default new Vuex.Store({
	modules: {
		data,
	},

	state: {
		tablesLoading: false,
		tables: [],
		views: [],
		activeTableId: null,
		activeViewId: null,
	},

	getters: {
		activeTable(state) {
			if (state.tables && state.tables.filter(item => item.id === state.activeTableId).length > 0) {
				return state.tables.filter(item => item.id === state.activeTableId)[0]
			}
			return null
		},
		getTable: (state) => (id) => {
			return state.tables.filter(table => table.id === id)[0]
		},
		activeView(state) {
			if (state.views && state.views.filter(item => item.id === state.activeViewId).length > 0) {
				return state.views.filter(item => item.id === state.activeViewId)[0]
			}
			return null
		},
	},
	mutations: {
		setTablesLoading(state, value) {
			state.tablesLoading = !!(value)
		},
		setActiveTableId(state, tableId) {
			if (state.activeTableId !== tableId) {
				state.activeTableId = tableId
				state.activeViewId = null
			}
		},
		setActiveViewId(state, viewId) {
			if (state.activeViewId !== viewId) {
				state.activeViewId = viewId
				state.activeTableId = null
			}
		},
		setTables(state, tables) {
			state.tables = tables
		},
		setViews(state, views) {
			state.views = views
		},
		setTable(state, table) {
			const index = state.tables.findIndex(t => t.id === table.id)
			state.tables[index] = table
		},
	},
	actions: {
		async insertNewTable({ commit, state }, { data }) {
			let res = null

			try {
				res = await axios.post(generateUrl('/apps/tables/table'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not insert table.'))
				return false
			}

			const tables = state.tables
			tables.push(res.data)
			commit('setTables', tables)
			return res.data.id
		},
		async loadTablesFromBE({ commit }) {
			commit('setTablesLoading', true)

			try {
				const res = await axios.get(generateUrl('/apps/tables/table'))
				commit('setTables', res.data)
			} catch (e) {
				displayError(e, t('tables', 'Could not load tables.'))
				showError(t('tables', 'Could not fetch tables'))
			}

			commit('setTablesLoading', false)
			return true
		},
		async loadViewsFromBE({ commit }) {
			commit('setTablesLoading', true)

			try {
				const res = await axios.get(generateUrl('/apps/tables/view/1')) //TODO
				commit('setViews', res.data)
			} catch (e) {
				displayError(e, t('tables', 'Could not load views.'))
				showError(t('tables', 'Could not fetch views'))
			}

			commit('setTablesLoading', false)
			return true
		},
		async updateTable({ state, commit, dispatch }, { id, data }) {
			let res = null

			try {
				res = await axios.put(generateUrl('/apps/tables/table/' + id), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not update table.'))
				return false
			}

			const table = res.data
			const tables = state.tables
			const index = tables.findIndex(t => t.id === table.id)
			tables[index] = table
			commit('setTables', [...tables])
			return true
		},
		async removeTable({ state, commit }, { tableId }) {
			try {
				await axios.delete(generateUrl('/apps/tables/table/' + tableId))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove table.'))
				return false
			}

			const tables = state.tables
			const index = tables.findIndex(t => t.id === tableId)
			tables.splice(index, 1)
			commit('setTables', [...tables])
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
