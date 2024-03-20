<!--
  - @copyright Copyright (c) 2023 Florian Steffens <flost-dev@mailbox.org>
  -
  - @author 2023 Florian Steffens <flost-dev@mailbox.org>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div v-if="richObject" class="tables-content-widget">
		<h2>{{ richObject.emoji }}&nbsp;{{ richObject.title }}</h2>
		<Options
			:config="tablePermissions"
			:show-options="true"
			@create-row="createRow"
			@set-search-string="search" />
		<div class="nc-table">
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
import { useResizeObserver } from '@vueuse/core'
import { spawnDialog } from '@nextcloud/dialogs'

export default {

	components: {
		NcTable,
		Options,
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
			rows: this.richObject.rows,
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
				const storeRows = Object.values(this.$store.data.state.rows).at(0)

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
				const storeRows = Object.values(this.$store.data.state.rows).at(0)
				const localRowIndex = this.rows.findIndex(row => row.id === rowId)
				const updatedRow = storeRows.find(row => row.id === rowId)
				this.rows.splice(localRowIndex, 1, updatedRow)
			})
		},
		getRow(rowId) {
			return this.rows.find(row => row.id === rowId)
		},
		async loadRows() {
			await this.$store.dispatch('loadRowsFromBE', { tableId: this.richObject.id })
		},
	},
}
</script>
<style lang="scss" scoped>

	.tables-content-widget {
		min-height: max(50vh, 200px);
		height: 50vh;
		overflow: scroll;

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
		}

		.nc-table {
			margin-left: calc(var(--default-grid-baseline) * 2);
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

			:where(.sticky) {
				border: none;
			}
		}

		& :deep(.options.row) {
			width: calc(var(--widget-content-width, 100%) - 12px);
		}
	}

</style>
