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
		showSidebar: false,
		sidebarActiveTab: '',
	},

	getters: {
		activeTable(state) {
			if (state.tables && state.tables.filter(item => item.id === state.activeTableId).length > 0) { return state.tables.filter(item => item.id === state.activeTableId)[0] }
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
			}
		},
		setTables(state, tables) {
			state.tables = tables
		},
		setShowSidebar(state, status) {
			state.showSidebar = !!status
		},
		setSidebarActiveTab(state, activeTab) {
			state.sidebarActiveTab = activeTab
		},
	},
	actions: {
		async loadTablesFromBE({ commit }) {
			commit('setTablesLoading', true)
			try {
				const response = await axios.get(generateUrl('/apps/tables/table'))
				commit('setTables', response.data)
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not fetch tables'))
			}
			commit('setTablesLoading', false)
		},
	},
})
