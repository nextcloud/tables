import Vue from 'vue'
import Vuex from 'vuex'
import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import data from './data.js'
import displayError from '../shared/utils/displayError.js'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../shared/constants.ts'

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
		activeViewId: null,
		activeTableId: null,
		activeRowId: null,
		activeElementIsView: false,
	},

	getters: {
		getTable: (state) => (id) => {
			return state.tables.find(table => table.id === id)
		},
		getView: (state) => (id) => {
			return state.views.find(view => view.id === id)
		},
		activeView(state) {
			if (state.views && state.activeViewId) {
				return state.views.find(item => item.id === state.activeViewId)
			}
			return null
		},
		activeTable(state) {
			if (state.tables && state.activeTableId) {
				return state.tables.find(item => item.id === state.activeTableId)
			}
			return null
		},
		activeElement(state) {
			if (state.activeTableId && state.tables) {
				return state.tables.find(item => item.id === state.activeTableId)
			} else if (state.views && state.activeViewId) {
				return state.views.find(item => item.id === state.activeViewId)
			}
			return null
		},
		isView(state) {
			return state.activeElementIsView
		},
	},
	mutations: {
		setTablesLoading(state, value) {
			state.tablesLoading = !!(value)
		},
		setActiveViewId(state, viewId) {
			if (state.activeViewId !== viewId) {
				state.activeViewId = viewId
				state.activeTableId = null
				state.activeElementIsView = true
			}
		},
		setActiveTableId(state, tableId) {
			if (state.activeTableId !== tableId) {
				state.activeTableId = tableId
				state.activeViewId = null
				state.activeElementIsView = false
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
		setView(state, view) {
			const index = state.views.findIndex(v => v.id === view.id)
			state.views[index] = view
		},
		setActiveRowId(state, rowId) {
			state.activeRowId = rowId
		},
	},
	actions: {
		async insertNewTable({ commit, state, dispatch }, { data }) {
			let res = null

			try {
				res = await axios.post(generateUrl('/apps/tables/table'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not insert table.'))
				return false
			}
			if (data.template !== 'custom') {
				await dispatch('loadTablesFromBE')
				await dispatch('loadViewsSharedWithMeFromBE')
			} else {
				const tables = state.tables
				tables.push(res.data)
				commit('setTables', tables)
			}
			return res.data
		},
		async loadTablesFromBE({ commit, state }) {
			commit('setTablesLoading', true)

			try {
				const res = await axios.get(generateUrl('/apps/tables/table'))
				commit('setTables', res.data)
				// Set Views
				state.views = []
				res.data.forEach(table => {
					if (table.views) state.views = state.views.concat(table.views)
				})
			} catch (e) {
				displayError(e, t('tables', 'Could not load tables.'))
				showError(t('tables', 'Could not fetch tables'))
			}

			commit('setTablesLoading', false)
			return true
		},
		async loadViewsSharedWithMeFromBE({ commit, state }) {
			commit('setTablesLoading', true)

			try {
				const res = await axios.get(generateUrl('/apps/tables/view'))
				res.data.forEach(view => {
					if (state.views.filter(v => v.id === view.id).length === 0) {
						state.views = state.views.concat(view)
					}
				})
			} catch (e) {
				displayError(e, t('tables', 'Could not load shared views.'))
				showError(t('tables', 'Could not load shared views'))
			}

			commit('setTablesLoading', false)
			return true
		},
		async insertNewView({ commit, state }, { data }) {
			let res = null

			try {
				res = await axios.post(generateUrl('/apps/tables/view'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not insert view.'))
				return false
			}

			const views = state.views
			views.push(res.data)
			commit('setViews', views)

			const tables = state.tables
			const table = tables.find(t => t.id === res.data.tableId)
			table.views.push(res.data)

			return res.data.id
		},
		async updateView({ state, commit, dispatch }, { id, data }) {
			let res = null

			try {
				res = await axios.put(generateUrl('/apps/tables/view/' + id), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not update view.'))
				return false
			}

			const view = res.data
			const views = state.views
			const index = views.findIndex(v => v.id === view.id)
			views[index] = view
			commit('setViews', [...views])
			return true
		},
		async removeView({ state, commit }, { viewId }) {
			try {
				await axios.delete(generateUrl('/apps/tables/view/' + viewId))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove view.'))
				return false
			}

			const views = state.views
			const index = views.findIndex(v => v.id === viewId)
			views.splice(index, 1)
			commit('setViews', [...views])
			return true
		},
		async reloadViewsOfTable({ state, commit }, { tableId }) {
			let res = null
			try {
				res = await axios.get(generateUrl('/apps/tables/view/table/' + tableId))
				// Set Views
				const views = state.views
				res.data.forEach(view => {
					const index = views.findIndex(v => v.id === view.id)
					views[index] = view
				})
				commit('setViews', [...views])
			} catch (e) {
				displayError(e, t('tables', 'Could not reload view.'))
				return false
			}
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
		async favoriteView({ state, commit, dispatch }, { id }) {
			try {
				await axios.post(generateOcsUrl(`/apps/tables/api/2/favorites/${NODE_TYPE_VIEW}/${id}`))
			} catch (e) {
				displayError(e, t('tables', 'Could not favorite view'))
				return false
			}

			const index = state.views.findIndex(v => v.id === id)
			const view = state.views[index]
			view.favorite = true
			commit('setView', view)

			return true
		},
		async removeFavoriteView({ state, commit, dispatch }, { id }) {
			try {
				await axios.delete(generateOcsUrl(`/apps/tables/api/2/favorites/${NODE_TYPE_VIEW}/${id}`))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove view from favorites'))
				return false
			}

			const index = state.views.findIndex(v => v.id === id)
			const view = state.views[index]
			view.favorite = false
			commit('setView', view)

			return true
		},
		async favoriteTable({ state, commit, dispatch }, { id }) {
			try {
				await axios.post(generateOcsUrl(`/apps/tables/api/2/favorites/${NODE_TYPE_TABLE}/${id}`))
			} catch (e) {
				displayError(e, t('tables', 'Could not favorite table'))
				return false
			}

			const index = state.tables.findIndex(t => t.id === id)
			const table = state.tables[index]
			table.favorite = true
			commit('setTable', table)

			return true
		},
		async removeFavoriteTable({ state, commit, dispatch }, { id }) {
			try {
				await axios.delete(generateOcsUrl(`/apps/tables/api/2/favorites/${NODE_TYPE_TABLE}/${id}`))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove table from favorites'))
				return false
			}

			const index = state.tables.findIndex(t => t.id === id)
			const table = state.tables[index]
			table.favorite = false
			commit('setTable', table)

			return true
		},
		async transferTable({ state, commit, dispatch }, { id, data }) {
			try {
				await axios.put(generateOcsUrl('/apps/tables/api/2/tables/' + id + '/transfer'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not transfer table.'))
				return false
			}

			const tables = state.tables
			const index = tables.findIndex(t => t.id === id)
			tables.splice(index, 1)
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
		setTableHasShares({ state, commit, getters }, { tableId, hasShares }) {
			const table = getters.getTable(tableId)
			table.hasShares = !!hasShares
			commit('setTable', table)
		},

		setViewHasShares({ state, commit, getters }, { viewId, hasShares }) {
			const view = getters.getView(viewId)
			view.hasShares = !!hasShares
			commit('setView', view)
		},
	},
})
