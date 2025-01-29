<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="richObject" class="tables-content-widget">
		<div class="header">
			<h2>
				<NcLoadingIcon v-if="!rows" :size="30" />
				<span v-else>{{ richObject.emoji }}</span>&nbsp;{{ richObject.title }}
			</h2>
			<Options
				:config="tablePermissions"
				:show-options="true"
				@create-row="createRow"
				@set-search-string="search" />
		</div>
		<div v-if="rows" class="nc-table">
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
import { spawnDialog } from '@nextcloud/dialogs'
import { useTablesStore } from '../store/store.js'
import { useDataStore } from '../store/data.js'
import { mapActions } from 'pinia'

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
			rows: null,
			tablesStore: useTablesStore(),
			dataStore: useDataStore(),
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
	},

	async mounted() {
		useResizeObserver(this.$el, (entries) => {
			const entry = entries[0]
			const { width } = entry.contentRect
			this.$el.style.setProperty('--widget-content-width', `${width}px`)
		})

		await this.loadRows()
	},

	methods: {
		...mapActions(useDataStore, ['loadRowsFromBE', 'getRows']),
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
			}, () => {
				const storeRows = this.getRows
				if (storeRows.length > this.rows.length) {
					const createdRow = storeRows.at(-1)
					this.rows.push(createdRow)
				}
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
			}, () => {
				const storeRows = this.getRows
				const localRowIndex = this.rows.findIndex(row => row.id === rowId)
				const updatedRow = storeRows.find(row => row.id === rowId)
				this.rows.splice(localRowIndex, 1, updatedRow)
			})
		},
		getRow(rowId) {
			return this.rows.find(row => row.id === rowId)
		},
		async loadRows() {
			const res = await this.loadRowsFromBE({
				tableId: this.richObject.id,
			})

			if (res) {
				this.rows = this.getRows
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
			left: 0;
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
				left: unset !important;
			}
		}

		& :deep(.options.row) {
			width: calc(var(--widget-content-width, 100%) - 12px);
		}
	}

</style>
