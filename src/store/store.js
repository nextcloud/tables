import Vue from 'vue'
import Vuex from 'vuex'
import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import data from './data.js'
import displayError from '../shared/utils/displayError.js'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../shared/constants.js'

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
		templates: [],
		contexts: [],
		contextsLoading: false,
		activeViewId: null,
		activeTableId: null,
		activeRowId: null,
		activeElementIsView: false,
		activeContextId: null,
		appNavCollapsed: false,
	},

	getters: {
		getTable: (state) => (id) => {
			return state.tables.find(table => table.id === id)
		},
		getContext: (state) => (id) => {
			return state.contexts.find(context => context.id === id)
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
		activeContext(state) {
			if (state.contexts && state.activeContextId) {
				return state.contexts.find(item => item.id === state.activeContextId)
			}
			return null
		},
		isView(state) {
			return state.activeElementIsView
		},
	},
	mutations: {
		setAppNavCollapsed(state, value) {
			state.appNavCollapsed = !!(value)
		},
		setTablesLoading(state, value) {
			state.tablesLoading = !!(value)
		},
		setContextsLoading(state, value) {
			state.contextsLoading = !!(value)
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
		setActiveContextId(state, contextId) {
			if (state.activeContextId !== contextId) {
				state.activeContextId = parseInt(contextId)
			}
		},
		setTables(state, tables) {
			state.tables = tables
		},
		setViews(state, views) {
			state.views = views
		},
		setTemplates(state, templates) {
			state.templates = templates
		},
		setContexts(state, contexts) {
			state.contexts = contexts
		},
		setTable(state, table) {
			const index = state.tables.findIndex(t => t.id === table.id)
			state.tables.splice(index, 1, table)
		},
		setView(state, view) {
			const index = state.views.findIndex(v => v.id === view.id)
			state.views[index] = view
		},
		setContext(state, context) {
			const index = state.contexts.findIndex(c => c.id === context.id)
			state.contexts[index] = context
		},
		setActiveRowId(state, rowId) {
			state.activeRowId = rowId
		},
	},
	actions: {
		async insertNewTable({ commit, state, dispatch }, { data }) {
			let res = null

			try {
				res = (await axios.post(generateOcsUrl('/apps/tables/api/2/tables'), data)).data.ocs
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
		async loadTemplatesFromBE({ commit }) {
			try {
				const res = await axios.get(generateUrl('/apps/tables/table/templates'))
				commit('setTemplates', res.data)
			} catch (e) {
				displayError(e, t('tables', 'Could not load templates.'))
				showError(t('tables', 'Could not fetch templates'))
			}
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
				res = (await axios.put(generateOcsUrl('/apps/tables/api/2/tables/' + id), data)).data.ocs
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
		async shareContext({ dispatch }, { id, previousReceivers, receivers }) {
			const share = {
				nodeType: 'context',
				nodeId: id,
				displayMode: 2,
			}
			try {
				for (const receiver of receivers) {
					share.receiverType = receiver.isUser ? 'user' : 'group'
					share.receiver = receiver.id
					// Avoid duplicate shares by checking if share exists first
					const existingShare = previousReceivers.find((p) => p.receiver === share.receiver && p.receiver_type === share.receiverType)
					if (!existingShare) {
						await axios.post(generateUrl('/apps/tables/share'), share)
					}
				}
			} catch (e) {
				displayError(e, t('tables', 'Could not add application share.'))
			}
			try {
				// If there's a previous share that wasn't maintained, delete it
				for (const previousReceiver of previousReceivers) {
					const currentShare = receivers.find((r) => {
						const receiverType = r.isUser ? 'user' : 'group'
						return r.id === previousReceiver.receiver && receiverType === previousReceiver.receiver_type
					})
					if (!currentShare) {
						await axios.delete(generateUrl('/apps/tables/share/' + previousReceiver.share_id))
					}
				}
			} catch (e) {
				displayError(e, t('tables', 'Could not remove application share.'))
			}
		},
		async insertNewContext({ commit, state, dispatch }, { data, receivers }) {
			commit('setContextsLoading', true)
			let res = null

			try {
				res = await axios.post(generateOcsUrl('/apps/tables/api/2/contexts'), data)
				const id = res?.data?.ocs?.data?.id
				if (id) {
					await dispatch('shareContext', { id, previousReceivers: [], receivers })
				}
			} catch (e) {
				displayError(e, t('tables', 'Could not insert application.'))
				return false
			}
			const contexts = state.contexts
			contexts.push(res.data.ocs.data)
			commit('setContexts', contexts)

			commit('setContextsLoading', false)
			return res.data.ocs.data
		},
		async updateContext({ state, commit, dispatch }, { id, data, previousReceivers, receivers }) {
			let res = null
			try {
				res = await axios.put(generateOcsUrl('/apps/tables/api/2/contexts/' + id), data)
				await dispatch('shareContext', { id, previousReceivers, receivers })

			} catch (e) {
				displayError(e, t('tables', 'Could not update application.'))
				return false
			}

			const context = res.data.ocs.data
			const contexts = state.contexts
			const index = contexts.findIndex(c => c.id === context.id)
			contexts[index] = context
			commit('setContexts', [...contexts])

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

		async getAllContexts({ commit, state }) {
			commit('setContextsLoading', true)
			try {
				const res = await axios.get(generateOcsUrl('/apps/tables/api/2/contexts'))
				commit('setContexts', res.data.ocs.data)
				await this.dispatch('getContextsTablesAndViews')
			} catch (e) {
				displayError(e, t('tables', 'Could not load applications.'))
				showError(t('tables', 'Could not fetch applications'))
			}
			commit('setContextsLoading', false)
			return true
		},

		async loadContext({ state, commit, dispatch }, { id }) {
			try {
				const res = await axios.get(generateOcsUrl('/apps/tables/api/2/contexts/' + id))
				commit('setContext', res.data.ocs.data)
			} catch (e) {
				displayError(e, t('tables', 'Could not load application.'))
				showError(t('tables', 'Could not fetch application'))
			}
			return true
		},
		async getContextsTablesAndViews({ state }) {
			for (const context of state.contexts) {
				for (const node of Object.values(context?.nodes)) {
					if (parseInt(node.node_type) === NODE_TYPE_TABLE) {
						await this.dispatch('loadContextTable', { id: node.node_id })
					} else if (parseInt(node.node_type) === NODE_TYPE_VIEW) {
						await this.dispatch('loadContextView', { id: node.node_id })
					}
				}

			}
		},

		async loadContextTable({ commit, state, getters }, { id }) {
			const table = getters.getTable(id)
			if (table) {
				return true
			}
			let res
			try {
				res = await axios.get(generateOcsUrl('/apps/tables/api/2/tables/' + id))
				const tables = state.tables
				tables.push(res.data.ocs.data)
				commit('setTables', tables)
			} catch (e) {
				displayError(e, t('tables', 'Could not load table.'))
				showError(t('tables', 'Could not fetch table'))
			}
			return res?.data.ocs.data
		},

		async loadContextView({ commit, state, getters }, { id }) {
			const view = getters.getView(id)
			if (view) {
				return true
			}
			let res
			try {
				res = await axios.get(generateUrl('/apps/tables/view/' + id))
				const views = state.views
				views.push(res.data)
				commit('setViews', views)
			} catch (e) {
				displayError(e, t('tables', 'Could not load view'))
				showError(t('tables', 'Could not fetch view'))
			}
			return res?.data
		},

		async transferContext({ state, commit, dispatch }, { id, data }) {
			try {
				await axios.put(generateOcsUrl('/apps/tables/api/2/contexts/' + id + '/transfer'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not transfer application.'))
				return false
			}

			const contexts = state.contexts
			const index = contexts.findIndex(c => c.id === id)
			contexts.splice(index, 1)
			commit('setContexts', [...contexts])
			return true
		},
		async removeContext({ state, commit }, { context }) {
			try {
				await axios.delete(generateOcsUrl('/apps/tables/api/2/contexts/' + context.id))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove application.'))
				return false
			}
			const contexts = state.contexts
			const index = contexts.findIndex(c => c.id === context.id)
			contexts.splice(index, 1)
			commit('setContexts', [...contexts])
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
