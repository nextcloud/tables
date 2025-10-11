/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import displayError from '../shared/utils/displayError.js'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../shared/constants.ts'
import { getCurrentUser } from '@nextcloud/auth'
import { set } from 'vue'

export const useTablesStore = defineStore('store', {
	state: () => ({
		loading: {
			tables: true,
			viewsShared: true,
			contexts: true,
		},
		tables: [],
		views: [],
		templates: [],
		contexts: [],
		activeViewId: null,
		activeTableId: null,
		activeRowId: null,
		activeElementIsView: false,
		activeContextId: null,
		appNavCollapsed: false,
	}),

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
		activeView: (state) => {
			if (state.views && state.activeViewId) {
				return state.views.find(item => item.id === state.activeViewId)
			}
			return null
		},
		activeTable: (state) => {
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
		activeContext: (state) => {
			if (state.contexts && state.activeContextId) {
				return state.contexts.find(item => item.id === state.activeContextId)
			}
			return null
		},

		isView: (state) => state.activeElementIsView,

		isLoading: (state) => (key) => state.loading[key] ?? false,

		isLoadingSomething: (state) => Object.keys(state.loading).filter(key => state.loading[key]).length > 0,
	},

	actions: {
		setAppNavCollapsed(value) {
			this.appNavCollapsed = !!value
		},

		setLoading({ key, value }) {
			this.loading[key] = !!value
		},

		setActiveViewId(viewId) {
			if (this.activeViewId !== viewId) {
				this.activeViewId = viewId
				this.activeTableId = null
				this.activeElementIsView = true
			}
		},

		setActiveTableId(tableId) {
			if (this.activeTableId !== tableId) {
				this.activeTableId = tableId
				this.activeViewId = null
				this.activeElementIsView = false
			}
		},
		setActiveContextId(contextId) {
			if (this.activeContextId !== contextId) {
				this.activeContextId = parseInt(contextId)
			}
		},
		setTables(tables) {
			this.tables = tables
		},
		setViews(views) {
			this.views = views
		},
		setTemplates(templates) {
			this.templates = templates
		},
		setContexts(contexts) {
			this.contexts = contexts
		},
		setTable(table) {
			const index = this.tables.findIndex(t => t.id === table.id)
			this.tables.splice(index, 1, table)
		},
		setView(view) {
			const index = this.views.findIndex(v => v.id === view.id)
			this.views[index] = view
		},
		setContext(context) {
			const index = this.contexts.findIndex(c => c.id === context.id)
			this.contexts[index] = context
		},
		setActiveRowId(rowId) {
			this.activeRowId = rowId
		},

		async insertNewTable({ data }) {
			let res = null

			try {
				res = (await axios.post(generateOcsUrl('/apps/tables/api/2/tables'), data)).data.ocs
			} catch (e) {
				displayError(e, t('tables', 'Could not insert table.'))
				return false
			}
			const tables = this.tables
			tables.push(res.data)
			this.setTables(tables)
			return res.data
		},

		async loadTablesFromBE() {
			this.setLoading({ key: 'tables', value: true })

			try {
				const res = await axios.get(generateUrl('/apps/tables/table'))
				this.setTables(res.data)
				this.views = []
				res.data.forEach(table => {
					if (table.views) this.views = this.views.concat(table.views)
				})
			} catch (e) {
				displayError(e, t('tables', 'Could not load tables.'))
				showError(t('tables', 'Could not fetch tables'))
			}

			this.setLoading({ key: 'tables', value: false })
			return true
		},

		async loadViewsSharedWithMeFromBE() {
			this.setLoading({ key: 'viewsShared', value: true })

			try {
				const res = await axios.get(generateUrl('/apps/tables/view'))
				res.data.forEach(view => {
					if (this.views.filter(v => v.id === view.id).length === 0) {
						this.views.push(view)
					}
				})
			} catch (e) {
				displayError(e, t('tables', 'Could not load shared views.'))
				showError(t('tables', 'Could not load shared views'))
			}

			this.setLoading({ key: 'viewsShared', value: false })
			return true
		},

		async loadTemplatesFromBE() {
			try {
				const res = await axios.get(generateUrl('/apps/tables/table/templates'))
				this.templates = res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not load templates.'))
				showError(t('tables', 'Could not fetch templates'))
			}
		},

		async insertNewView({ data }) {
			let res = null
			try {
				res = await axios.post(generateUrl('/apps/tables/view'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not insert view.'))
				return false
			}

			const views = this.views
			views.push(res.data)
			this.setViews(views)
			const table = this.tables.find(t => t.id === res.data.tableId)
			table.views.push(res.data)

			return res.data.id
		},

		async updateView({ id, data }) {
			let res = null

			try {
				res = await axios.put(generateUrl('/apps/tables/view/' + id), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not update view.'))
				return false
			}

			const view = res.data
			const views = this.views
			const index = views.findIndex(v => v.id === view.id)
			views[index] = view
			this.setViews([...views])
			return true
		},

		async removeView({ viewId }) {
			try {
				await axios.delete(generateUrl('/apps/tables/view/' + viewId))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove view.'))
				return false
			}

			const views = this.views
			const index = views.findIndex(v => v.id === viewId)
			views.splice(index, 1)
			this.setViews([...views])
			return true
		},

		async reloadViewsOfTable({ tableId }) {
			let res = null
			try {
				res = await axios.get(generateUrl('/apps/tables/view/table/' + tableId))
				// Set Views
				const views = this.views
				res.data.forEach(view => {
					const index = views.findIndex(v => v.id === view.id)
					set(this.views, index, view)
				})
			} catch (e) {
				displayError(e, t('tables', 'Could not reload view.'))
				return false
			}
			return true
		},

		async updateTable({ id, data }) {
			let res = null

			try {
				res = (await axios.put(generateOcsUrl('/apps/tables/api/2/tables/' + id), data)).data.ocs
			} catch (e) {
				displayError(e, t('tables', 'Could not update table.'))
				return false
			}

			const table = res.data
			const tables = this.tables
			const index = tables.findIndex(t => t.id === table.id)
			set(this.tables, index, table)
			return true
		},

		async favoriteView({ id }) {
			try {
				await axios.post(generateOcsUrl(`/apps/tables/api/2/favorites/${NODE_TYPE_VIEW}/${id}`))
			} catch (e) {
				displayError(e, t('tables', 'Could not mark view as favorite'))
				return false
			}

			const index = this.views.findIndex(v => v.id === id)
			const view = this.views[index]
			view.favorite = true
			this.setView(view)

			return true
		},

		async removeFavoriteView({ id }) {
			try {
				await axios.delete(generateOcsUrl(`/apps/tables/api/2/favorites/${NODE_TYPE_VIEW}/${id}`))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove view from favorites'))
				return false
			}

			const index = this.views.findIndex(v => v.id === id)
			const view = this.views[index]
			view.favorite = false
			this.setView(view)

			return true
		},

		async favoriteTable({ id }) {
			try {
				await axios.post(generateOcsUrl(`/apps/tables/api/2/favorites/${NODE_TYPE_TABLE}/${id}`))
			} catch (e) {
				displayError(e, t('tables', 'Could not mark table as favorite'))
				return false
			}

			const index = this.tables.findIndex(t => t.id === id)
			const table = this.tables[index]
			table.favorite = true
			this.setTable(table)

			return true
		},

		async removeFavoriteTable({ id }) {
			try {
				await axios.delete(generateOcsUrl(`/apps/tables/api/2/favorites/${NODE_TYPE_TABLE}/${id}`))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove table from favorites'))
				return false
			}

			const index = this.tables.findIndex(t => t.id === id)
			const table = this.tables[index]
			table.favorite = false
			this.setTable(table)

			return true
		},

		async shareContext({ id, previousReceivers, receivers, displayMode }) {
			const share = {
				nodeType: 'context',
				nodeId: id,
				displayMode,
			}
			try {
				for (const receiver of receivers) {
					share.receiverType = receiver.isUser ? 'user' : 'group'
					share.receiver = receiver.id
					// Avoid duplicate shares by checking if share exists first
					const existingShare = previousReceivers.find((p) => p.receiver === share.receiver && p.receiver_type === share.receiverType)
					if (!existingShare) {
						const createdShare = await axios.post(generateUrl('/apps/tables/share'), share)
						if (createdShare?.data && createdShare?.data?.id) {
							const shareId = createdShare.data.id
							await this.updateDisplayMode({ shareId, displayMode, target: 'default' })
							if (receiver.id === getCurrentUser().uid) {
								await this.updateDisplayMode({ shareId, displayMode, target: 'self' })
							}
						}
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
					} else {
						const shareId = previousReceiver.share_id
						await this.updateDisplayMode({ shareId, displayMode, target: 'default' })
					}
				}
			} catch (e) {
				displayError(e, t('tables', 'Could not remove application share.'))
			}
		},

		async updateDisplayMode({ shareId, displayMode, target }) {
			try {
				await axios.put(generateUrl('/apps/tables/share/' + shareId + '/display-mode'), { displayMode, target })
			} catch (e) {
				displayError(e, t('tables', 'Could not update display mode.'))
			}
		},

		async insertNewContext({ data, receivers, displayMode }) {
			this.setLoading({ key: 'contexts', value: true })
			let res = null

			try {
				res = await axios.post(generateOcsUrl('/apps/tables/api/2/contexts'), data)
				const id = res?.data?.ocs?.data?.id
				if (id) {
					await this.shareContext({ id, previousReceivers: [], receivers, displayMode })
				}
			} catch (e) {
				displayError(e, t('tables', 'Could not insert application.'))
				return false
			}
			const contexts = this.contexts
			contexts.push(res.data.ocs.data)
			this.setContexts(contexts)

			this.setLoading({ key: 'contexts', value: false })
			return res.data.ocs.data
		},

		async updateContext({ id, data, previousReceivers, receivers, displayMode }) {
			let res = null
			try {
				res = await axios.put(generateOcsUrl('/apps/tables/api/2/contexts/' + id), data)
				await this.shareContext({ id, previousReceivers, receivers, displayMode })
			} catch (e) {
				displayError(e, t('tables', 'Could not update application.'))
				return false
			}

			const context = res.data.ocs.data
			const index = this.contexts.findIndex(c => c.id === context.id)
			set(this.contexts, index, context)

			return true
		},

		async transferTable({ id, data }) {
			try {
				await axios.put(generateOcsUrl('/apps/tables/api/2/tables/' + id + '/transfer'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not transfer table.'))
				return false
			}

			const tables = this.tables
			const index = tables.findIndex(t => t.id === id)
			tables.splice(index, 1)
			this.setTables([...tables])
			return true
		},

		async getAllContexts() {
			this.setLoading({ key: 'contexts', value: true })
			try {
				const res = await axios.get(generateOcsUrl('/apps/tables/api/2/contexts'))
				this.contexts = res.data.ocs.data
				await this.getContextsTablesAndViews()
			} catch (e) {
				displayError(e, t('tables', 'Could not load applications.'))
				showError(t('tables', 'Could not fetch applications'))
			}
			this.setLoading({ key: 'contexts', value: false })
			return true
		},

		async loadContext({ id }) {
			try {
				const res = await axios.get(generateOcsUrl('/apps/tables/api/2/contexts/' + id))
				this.setContext(res.data.ocs.data)
			} catch (e) {
				if (e?.response?.status === 404) {
					throw new Error('NOT_FOUND')
				}
				displayError(e, t('tables', 'Could not load application.'))
				showError(t('tables', 'Could not fetch application'))
				throw e
			}
			return true
		},

		async getContextsTablesAndViews() {
			for (const context of this.contexts) {
				for (const node of Object.values(context?.nodes)) {
					if (parseInt(node.node_type) === NODE_TYPE_TABLE) {
						await this.loadContextTable({ id: node.node_id })
					} else if (parseInt(node.node_type) === NODE_TYPE_VIEW) {
						await this.loadContextView({ id: node.node_id })
					}
				}

			}
		},

		async loadContextTable({ id }) {
			id = parseInt(id)
			const table = this.tables.find(table => table.id === id)
			if (table) {
				return true
			}
			let res
			try {
				res = await axios.get(generateOcsUrl('/apps/tables/api/2/tables/' + id))
				const tables = this.tables
				tables.push(res.data.ocs.data)
				this.setTables([...tables])
			} catch (e) {
				if (e?.response?.status === 404) {
					throw new Error('NOT_FOUND')
				}
				displayError(e, t('tables', 'Could not load table.'))
				showError(t('tables', 'Could not fetch table'))
				throw e
			}
			return res?.data.ocs.data
		},

		async loadContextView({ id }) {
			id = parseInt(id)
			const view = this.views.find(view => view.id === id)
			if (view) {
				return true
			}
			let res
			try {
				res = await axios.get(generateUrl('/apps/tables/view/' + id))
				const views = this.views
				views.push(res.data)
				this.setViews([...views])
			} catch (e) {
				if (e?.response?.status === 404) {
					throw new Error('NOT_FOUND')
				}
				displayError(e, t('tables', 'Could not load view'))
				showError(t('tables', 'Could not fetch view'))
				throw e
			}
			return res?.data
		},

		async transferContext({ id, data }) {
			try {
				await axios.put(generateOcsUrl('/apps/tables/api/2/contexts/' + id + '/transfer'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not transfer application.'))
				return false
			}

			const contexts = this.contexts
			const index = contexts.findIndex(c => c.id === id)
			contexts.splice(index, 1)
			this.setContexts([...contexts])
			return true
		},

		async removeContext({ context }) {
			try {
				await axios.delete(generateOcsUrl('/apps/tables/api/2/contexts/' + context.id))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove application.'))
				return false
			}
			const contexts = this.contexts
			const index = contexts.findIndex(c => c.id === context.id)
			contexts.splice(index, 1)
			this.setContexts([...contexts])
			return true
		},

		async removeTable({ tableId }) {
			try {
				await axios.delete(generateUrl('/apps/tables/table/' + tableId))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove table.'))
				return false
			}

			const tables = this.tables
			const index = tables.findIndex(t => t.id === tableId)
			tables.splice(index, 1)
			this.setTables([...tables])
			return true
		},

		setTableHasShares({ tableId, hasShares }) {
			const table = this.tables.find(t => t.id === tableId)
			if (table) {
				table.hasShares = !!hasShares
			}
		},

		setViewHasShares({ viewId, hasShares }) {
			const view = this.views.find(v => v.id === viewId)
			if (view) {
				view.hasShares = !!hasShares
			}
		},

	},
})
