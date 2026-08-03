<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="richObject" class="tables-content-widget" data-cy="contentReferenceWidget">
		<div class="header">
			<h2>
				<NcLoadingIcon v-if="!rows" :size="30" />
				<span v-else>{{ richObject.emoji }}</span> {{ richObject.title }}
			</h2>
			<Options
				:config="tablePermissions"
				:rows="filteredRows"
				:show-options="true"
				@create-row="createRow"
				@set-search-string="search" />
		</div>
		<div v-if="rows && rows.length > 0" class="nc-table">
			<NcTable
				:rows="filteredRows"
				:columns="columns"
				:element-id="richObject.id"
				:is-view="isView"
				v-model:view-setting="localViewSetting"
				v-bind="tablePermissions"
				@edit-row="editRow"
				@copy-row="copyRow"
				@delete-row="deleteRow" />
		</div>
		<CreateRow
			:columns="columns"
			:is-view="isView"
			:element-id="richObject.id"
			:show-modal="showCopyRow"
			:prefill-data="copyPrefillData"
			@close="showCopyRow = false; copyPrefillData = null" />
		<DeleteRows
			v-if="rowToDelete !== null"
			:rows-to-delete="[rowToDelete]"
			:element-id="richObject.id"
			:is-view="isView"
			@cancel="rowToDelete = null" />
	</div>
</template>
 
<script>
import NcTable from '../shared/components/ncTable/NcTable.vue'
import Options from '../shared/components/ncTable/sections/Options.vue'
import CreateRow from '../modules/modals/CreateRow.vue'
import DeleteRows from '../modules/modals/DeleteRows.vue'
import permissionsMixin from '../shared/components/ncTable/mixins/permissionsMixin.js'
import { NcLoadingIcon } from '@nextcloud/vue'
import { useResizeObserver } from '@vueuse/core'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { useTablesStore } from '../store/store.js'
import { useDataStore } from '../store/data.js'
 
export default {
 
	components: {
		NcTable,
		Options,
		CreateRow,
		DeleteRows,
		NcLoadingIcon,
	},
 
	mixins: [permissionsMixin],
 
	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},
 
	data() {
		return {
			searchExp: null,
			localRows: [], // Keep as fallback only
			localViewSetting: {},
			showCopyRow: false,
			copyPrefillData: null,
			rowToDelete: null,
			tablesStore: null,
			dataStore: null,
		}
	},
 
	computed: {
		isView() {
			return Boolean(this.richObject?.type)
		},
		tablePermissions() {
			return {
				canCreateRows: this.canCreateRowInElement(this.richObject),
				canReadRows: true,
				canEditRows: this.canUpdateData(this.richObject),
				canDeleteRows: this.canDeleteData(this.richObject),
				canCreateColumns: false,
				canEditColumns: false,
				canDeleteColumns: false,
				canDeleteTable: false,
				canSelectRows: false,
				canHideColumns: false,
				canFilter: false,
				showActions: this.canCreateRowInElement(this.richObject) || this.canUpdateData(this.richObject) || this.canDeleteData(this.richObject),
			}
		},
		filteredRows() {
			if (this.searchExp) {
				return this.rows.filter(row => {
					return row.data.some(column => {
						const col = String(column.value)
						return col.search(this.searchExp) >= 0
					})
				})
			} else {
				return this.rows
			}
		},
		getRows() {
			return this.dataStore ? this.dataStore.getRows(this.isView, this.richObject.id) : []
		},
		// Use computed property to get rows from store or richObject
		rows() {
			// First try to get from the store
			const storeRows = this.getRows
			if (storeRows && storeRows.length > 0) {
				return storeRows
			}
			// Fallback to richObject rows or local rows
			return this.richObject?.rows || this.localRows
		},
		getColumns() {
			return this.dataStore ? this.dataStore.getColumns(this.isView, this.richObject.id) : []
		},
		// Prefer fresh store data over the (possibly stale) richObject snapshot
		columns() {
			const storeColumns = this.getColumns
			if (storeColumns && storeColumns.length > 0) {
				return storeColumns
			}
			return this.richObject?.columns || []
		},
	},
 
	watch: {
		richObject: {
			deep: true,
			handler(newVal) {
				if (newVal && newVal.rows && this.localRows !== newVal.rows) {
					this.localRows = newVal.rows
				}
			},
		},
		rows: {
			deep: true,
			handler(newRows) {
				if (this.richObject && newRows) {
					/* eslint-disable vue/no-mutating-props */
					this.richObject.rows = newRows
					this.richObject.rowsCount = newRows.length
					/* eslint-enable vue/no-mutating-props */
				}
				// Force update of filteredRows when rows change
				this.search(this.searchExp ? this.searchExp.source : '')
			},
		},
	},
 
	async mounted() {
		useResizeObserver(this.$el, (entries) => {
			const entry = entries[0]
			const { width } = entry.contentRect
			// In Vue 3 $el can be a fragment/comment node (no style), so guard it.
			this.$el?.style?.setProperty?.('--widget-content-width', `${width}px`)
		})
 
		this.tablesStore = useTablesStore()
		this.dataStore = useDataStore()
 
		await Promise.all([this.loadRows(), this.loadColumns()])
	},
 
	methods: {
		// { tableId } or { viewId } payload for loadRowsFromBE
		elementIdPayload() {
			return this.isView
				? { viewId: this.richObject.id }
				: { tableId: this.richObject.id }
		},
		search(searchString) {
			this.searchExp = (searchString !== '')
				? new RegExp(searchString.trim(), 'ig')
				: null
		},
		async createRow() {
			const { default: CreateRow } = await import('../modules/modals/CreateRow.vue')
			spawnDialog(CreateRow, {
				showModal: true,
				columns: this.columns,
				isView: this.isView,
				elementId: this.richObject.id,
			}, async () => {
				// Reload rows from the backend to get the latest data
				await this.dataStore.loadRowsFromBE(this.elementIdPayload())
			})
		},
		async editRow(rowId) {
			const { default: EditRow } = await import('../modules/modals/EditRow.vue')
			spawnDialog(EditRow, {
				showModal: true,
				columns: this.columns,
				row: this.getRow(rowId),
				isView: this.isView,
				element: this.richObject,
			}, async () => {
				await this.dataStore.loadRowsFromBE(this.elementIdPayload())
			})
		},
		copyRow(rowId) {
			this.copyPrefillData = this.getRow(rowId)?.data
			this.showCopyRow = true
		},
		deleteRow(rowId) {
			this.rowToDelete = rowId
		},
		getRow(rowId) {
			return this.rows.find(row => row.id === rowId)
		},
		async loadRows() {
			if (!this.dataStore) return
 
			// Paint from cached snapshot immediately, but it can be stale --
			// always reconcile with the backend below.
			if (this.richObject.rows) {
				this.localRows = this.richObject.rows
				this.dataStore.seedRows({
					isView: this.isView,
					elementId: this.richObject.id,
					rows: this.richObject.rows,
				})
			}
 
			try {
				await this.dataStore.loadRowsFromBE(this.elementIdPayload())
				// No need to set local rows as the computed property will use store data
			} catch (error) {
				console.error('Error loading rows:', error)
			}
		},
		async loadColumns() {
			if (!this.dataStore) return
			try {
				if (this.isView) {
					await this.dataStore.loadColumnsFromBE({ view: this.richObject })
				} else {
					await this.dataStore.loadColumnsFromBE({ tableId: this.richObject.id })
				}
			} catch (error) {
				console.error('Error loading columns:', error)
			}
		},
	},
}
</script>
<style lang="scss" scoped>

	.tables-content-widget {
		min-height: max(50vh, 200px);
		height: auto;
		max-height: calc(100dvh - 40px);
		overflow: scroll;
		overscroll-behavior: contain;
		isolation: isolate;

		& .header {
			position: sticky;
			top: 0;
			inset-inline-start: 0;
			z-index: 7;
			background-color: var(--color-main-background);

			:where(.options) {
				position: sticky;
				top: 57px;
				z-index: 1;
				padding-bottom: 10px;
				background-color: var(--color-main-background);
			}

			h2 {
				position: sticky;
				top: 0;
				min-width: var(--widget-content-width);
				z-index: 1;
				background-color: var(--color-main-background);
				margin: 0 !important;
				padding: calc(var(--default-grid-baseline) * 4);

				& .loading-icon {
					display: inline-block;
					vertical-align: middle;
				}
			}
		}

		.nc-table {
			min-width: var(--widget-content-width);

			:deep(.options.row) {
				height: 0 !important;
				overflow: hidden !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			:where(thead) {
				position: sticky;
				top: 117px;

				:where(.cell-wrapper) {
					min-width: 150px;
					max-width: 200px;
				}

				:where(.sticky) {
					background: transparent !important;
				}
			}
		}

		& :deep(.options.row) {
			width: calc(var(--widget-content-width, 100%) - 12px);
		}

		& :deep(td) {
			vertical-align: middle !important;
		}
	}

</style>
