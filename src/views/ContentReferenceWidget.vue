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
				:show-options="true"
				@create-row="createRow"
				@set-search-string="search" />
		</div>
		<div v-if="rows && rows.length > 0" class="nc-table">
			<NcTable
				:rows="filteredRows"
				:columns="richObject.columns"
				v-bind="tablePermissions"
				@edit-row="editRow" />
		</div>
	</div>
</template>

<script>
import NcTable from '../shared/components/ncTable/NcTable.vue'
import Options from '../shared/components/ncTable/sections/Options.vue'
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
			tablesStore: null,
			dataStore: null,
		}
	},

	computed: {
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
				showActions: this.canManageElement(this.richObject),
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
			return this.dataStore ? this.dataStore.getRows(false, this.richObject.id) : []
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
				// Sync changes back to richObject for reactivity
				if (this.richObject && newRows) {
					this.$set(this.richObject, 'rows', newRows)
					// Update row count
					this.$set(this.richObject, 'rowsCount', newRows.length)
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
			this.$el.style.setProperty('--widget-content-width', `${width}px`)
		})

		this.tablesStore = useTablesStore()
		this.dataStore = useDataStore()

		await this.loadRows()
	},

	methods: {
		search(searchString) {
			this.searchExp = (searchString !== '')
				? new RegExp(searchString.trim(), 'ig')
				: null
		},
		async createRow() {
			const { default: CreateRow } = await import('../modules/modals/CreateRow.vue')
			spawnDialog(CreateRow, {
				showModal: true,
				columns: this.richObject.columns,
				isView: Boolean(this.richObject.type),
				elementId: this.richObject.id,
			}, async () => {
				// Reload rows from the backend to get the latest data
				await this.dataStore.loadRowsFromBE({
					tableId: this.richObject.id,
				})
			})
		},
		async editRow(rowId) {
			const { default: EditRow } = await import('../modules/modals/EditRow.vue')
			spawnDialog(EditRow, {
				showModal: true,
				columns: this.richObject.columns,
				row: this.getRow(rowId),
				isView: Boolean(this.richObject.type),
				element: this.richObject,
			}, async () => {
				// Reload rows from the backend to get the latest data
				await this.dataStore.loadRowsFromBE({
					tableId: this.richObject.id,
				})
			})
		},
		getRow(rowId) {
			return this.rows.find(row => row.id === rowId)
		},
		async loadRows() {
			if (!this.dataStore) return

			if (this.richObject.rows) {
				this.localRows = this.richObject.rows
				return
			}

			try {
				await this.dataStore.loadRowsFromBE({
					tableId: this.richObject.id,
				})
				// No need to set local rows as the computed property will use store data
			} catch (error) {
				console.error('Error loading rows:', error)
			}
		},
	},
}
</script>
<style lang="scss" scoped>

	.tables-content-widget {
		min-height: max(50vh, 200px);
		height: 50vh;
		overflow: scroll;

		& .header {
			position: sticky;
			top: 0;
			inset-inline-start: 0;
			z-index: 1;

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

			:where(.options.row) {
				display: none;
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

			:where(.pagination-footer) {
				width: unset !important;
				inset-inline-start: unset !important;
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
