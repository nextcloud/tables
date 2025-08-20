/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { defineStore } from 'pinia'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import displayError from '../shared/utils/displayError.js'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'
import { MetaColumns } from '../shared/components/ncTable/mixins/metaColumns.js'
import { showError } from '@nextcloud/dialogs'
import { set } from 'vue'
import { emit } from '@nextcloud/event-bus'

function genStateKey(isView, elementId) {
	elementId = elementId.toString()
	return isView ? 'view-' + elementId : elementId
}

export const useDataStore = defineStore('data', {
	state: () => ({
		loading: {},
		rows: {},
		columns: {},
	}),

	getters: {
		getColumns: (state) => (isView, elementId) => {
			const stateId = genStateKey(isView, elementId)
			return state.columns[stateId] ?? []
		},
		getRows: (state) => (isView, elementId) => {
			const stateId = genStateKey(isView, elementId)
			return state.rows[stateId] ?? []
		},
	},

	actions: {
		clearState() {
			this.loading = {}
			this.columns = {}
			this.rows = {}
		},

		// COLUMNS
		async getColumnsFromBE({ tableId, viewId }) {
			const stateId = genStateKey(!!(viewId), viewId ?? tableId)
			this.loading[stateId] = true
			let res = null

			try {
				if (tableId && viewId) {
					res = await axios.get(generateUrl('/apps/tables/api/1/tables/' + tableId + '/columns') + '?viewId=' + viewId)
				} else if (tableId && !viewId) {
					// Get all table columns without view. Table manage rights needed
					res = await axios.get(generateUrl('/apps/tables/api/1/tables/' + tableId + '/columns'))
				} else if (!tableId && viewId) {
					// Get all view columns.
					res = await axios.get(generateUrl('/apps/tables/api/1/views/' + viewId + '/columns'))
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
			this.loading[stateId] = false
			return columns
		},

		async loadColumnsFromBE({ view, tableId }) {
			let allColumns = await this.getColumnsFromBE({ tableId, viewId: view?.id })
			if (view) {
				// Transform array to object for faster access
				const columnSettingsMap = view.columnSettings?.reduce((acc, item) => {
					acc[item.columnId] = item
					return acc
				}, {}) ?? {}

				allColumns = allColumns.concat(MetaColumns.filter(col => columnSettingsMap[col.id]))
				if (view.columnSettings) {
					allColumns = allColumns.sort((a, b) => {
						const orderA = columnSettingsMap[a.id]?.order ?? Number.MAX_SAFE_INTEGER
						const orderB = columnSettingsMap[b.id]?.order ?? Number.MAX_SAFE_INTEGER
						return orderA - orderB
					})
				}
			}
			const stateId = genStateKey(!!(view?.id), view?.id ?? tableId)
			set(this.columns, stateId, allColumns)
			return true
		},

		async insertNewColumn({ isView, elementId, data }) {
			const stateId = genStateKey(isView, elementId)
			this.loading[stateId] = true
			let res = null

			try {
				res = await axios.post(generateUrl('/apps/tables/api/1/columns'), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not insert column.'))
				return false
			}

			if (stateId) {
				this.columns[stateId].push(parseCol(res.data))
				this.loading[stateId] = false
			}
			return true
		},

		async updateColumn({ id, isView, elementId, data }) {
			data.selectionOptions = JSON.stringify(data.selectionOptions)
			data.usergroupDefault = JSON.stringify(data.usergroupDefault)
			let res = null

			try {
				res = await axios.put(generateUrl('/apps/tables/api/1/columns/' + id), data)
			} catch (e) {
				displayError(e, t('tables', 'Could not update column.'))
				return false
			}

			const stateId = genStateKey(isView, elementId)
			if (stateId && this.columns[stateId]) {
				const col = res.data
				const index = this.columns[stateId].findIndex(c => c.id === col.id)
				set(this.columns[stateId], index, parseCol(col))
			}

			return true
		},

		async removeColumn({ id, isView, elementId }) {
			try {
				await axios.delete(generateUrl('/apps/tables/api/1/columns/' + id))
			} catch (e) {
				displayError(e, t('tables', 'Could not remove column.'))
				return false
			}

			const stateId = genStateKey(isView, elementId)
			if (stateId && this.columns[stateId]) {
				const filteredColumns = this.columns[stateId].filter(c => c.id !== id)
				set(this.columns, stateId, filteredColumns)
			}

			return true
		},

		// ROWS
		async loadRowsFromBE({ tableId, viewId }) {
			const stateId = genStateKey(!!(viewId), viewId ?? tableId)
			this.loading[stateId] = true
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

			set(this.rows, stateId, res.data)
			this.loading[stateId] = false
			return true
		},

		removeRows({ isView, elementId }) {
			const stateId = genStateKey(isView, elementId)
			set(this.rows, stateId, [])
		},

		async updateRow({ id, isView, elementId, data }) {
			let res = null
			const viewId = isView ? elementId : null

			try {
				res = await axios.put(generateUrl('/apps/tables/row/' + id), { viewId, data })
			} catch (e) {
				console.debug(e?.response)
				if (e?.response?.data?.message?.startsWith('User should not be able to access row')) {
					showError(t('tables', 'Outdated data. View is reloaded'))
					await this.loadRowsFromBE({ viewId })
				} else {
					displayError(e, t('tables', 'Could not update row.'))
				}
				return false
			}

			const stateId = genStateKey(isView, elementId)
			if (stateId && this.rows[stateId]) {
				const row = res.data
				const index = this.rows[stateId].findIndex(r => r.id === row.id)
				set(this.rows[stateId], index, row)
				await this.removeRowIfNotInView({ rowId: row?.id, viewId, stateId })
			}

			return true
		},

		async insertNewRow({ viewId, tableId, data }) {
			let res = null

			try {
				const collection = viewId == null ? 'tables' : 'views'
				const nodeId = viewId == null ? tableId : viewId
				res = await axios.post(generateOcsUrl('/apps/tables/api/2/' + collection + '/' + nodeId + '/rows'), { data })
			} catch (e) {
				displayError(e, t('tables', 'Could not insert row.'))
				return false
			}

			const stateId = genStateKey(!!(viewId), viewId ?? tableId)
			if (stateId && this.rows[stateId]) {
				const row = res?.data?.ocs?.data
				const newIndex = this.rows[stateId].length
				set(this.rows[stateId], newIndex, row)
				await this.removeRowIfNotInView({ rowId: row?.id, viewId, stateId })
			}

			return true
		},

		async removeRow({ rowId, isView, elementId }) {
			const viewId = isView ? elementId : null
			try {
				if (viewId) {
					await axios.delete(generateUrl('/apps/tables/view/' + viewId + '/row/' + rowId))
				} else {
					await axios.delete(generateUrl('/apps/tables/row/' + rowId))
				}
			} catch (e) {
				if (e?.response?.data?.message?.startsWith('User should not be able to access row')) {
					showError(t('tables', 'Outdated data. View is reloaded'))
					await this.loadRowsFromBE({ viewId })
				} else {
					displayError(e, t('tables', 'Could not remove row.'))
				}
				return false
			}

			const stateId = genStateKey(isView, elementId)
			if (stateId && this.rows[stateId]) {
				const filteredRows = this.rows[stateId].filter(r => r.id !== rowId)
				set(this.rows, stateId, filteredRows)
			}
			return true
		},

		async checkRowInView({ rowId, viewId }) {
			try {
				const res = await axios.get(generateUrl('/apps/tables/view/{viewId}/row/{rowId}/present', { viewId, rowId }))
				return res.data.present
			} catch (e) {
				showError(t('tables', 'Could not verify row. View is reloaded'))
				await this.loadRowsFromBE({ viewId })
			}
		},

		async removeRowIfNotInView({ rowId, viewId, stateId }) {
			if (!rowId || !viewId) {
				return
			}

			const rowInView = await this.checkRowInView({ rowId, viewId })
			if (rowInView === false) {
				emit('tables:row:animate')
				this.rows[stateId] = this.rows[stateId].filter(r => r.id !== rowId)
			}
		},
	},

})
