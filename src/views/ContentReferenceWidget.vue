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
		<h2>{{ element.emoji }}&nbsp;{{ element.title }}</h2>
		<Options
			:config="tablePermissions"
			:show-options="true"
			@create-row="createRow" />
		<div class="nc-table">
			<NcTable
				:rows="element.rows"
				:columns="element.columns"
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
			element: {
				...this.richObject,
				isView: this.richObject.link.includes('view'),
			},
		}
	},

	computed: {
		tableId() {
			const id = this.richObject.link.split('/').at(-2)
			return parseInt(id)
		},
		tablePermissions() {
			return {
				canCreateRows: this.canCreateRowInElement(this.element),
				canReadRows: true,
				canEditRows: this.canUpdateData(this.element),
				canDeleteRows: this.canDeleteData(this.element),
				canCreateColumns: false,
				canEditColumns: false,
				canDeleteColumns: false,
				canDeleteTable: false,
				canSelectRows: false,
				canHideColumns: false,
				canFilter: false,
				showActions: this.canManageElement(this.element),
			}
		},
	},

	mounted() {
		useResizeObserver(this.$el, (entries) => {
			const entry = entries[0]
			const { width } = entry.contentRect
			this.$el.style.setProperty('--widget-content-width', `${width}px`)
		})
	},

	methods: {
		async createRow() {
			if (!this.$store) {
				await this.loadStore()
			}

			const { default: CreateRow } = await import('../modules/modals/CreateRow.vue')
			spawnDialog(CreateRow, {
				showModal: true,
				columns: this.element.columns,
				isView: this.element.isView,
				elementId: this.tableId,
			}, async () => {
				const storeRows = this.$store.data.state.rows

				if (storeRows.length > this.element.rows.length) {
					const createdRow = this.$store.data.state.rows.at(-1)
					this.element.rows.push(createdRow)
				}
			})
		},
		async editRow(rowId) {
			if (!this.$store) {
				await this.loadStore()
			}

			const { default: EditRow } = await import('../modules/modals/EditRow.vue')
			spawnDialog(EditRow, {
				showModal: true,
				columns: this.element.columns,
				row: this.getRow(rowId),
				isView: this.element.isView,
				element: this.element,
			}, async () => {
				const localRowIndex = this.element.rows.findIndex(row => row.id === rowId)
				const updatedRow = this.$store.data.state.rows.find(row => row.id === rowId)
				this.element.rows.splice(localRowIndex, 1, updatedRow)
			})
		},
		getRow(rowId) {
			return this.element.rows.find(row => row.id === rowId)
		},
		async loadStore() {
			const { default: store } = await import(/* webpackChunkName: 'store' */ '../store/store.js')
			const { default: data } = await import(/* webpackChunkName: 'store' */ '../store/data.js')

			this.$store = store
			this.$store.data = data

			await this.$store.dispatch('loadRowsFromBE', { tableId: this.tableId })
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
			width: calc(var(--widget-content-width, 100%) - 24px);
			z-index: 1;
			background-color: var(--color-main-background);
			margin: 0 !important;
			padding: calc(var(--default-grid-baseline) * 3);
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
			}

			:where(.sticky) {
				border-width: 0;
			}
		}

		& :deep(.options.row) {
			width: calc(var(--widget-content-width, 100%) - 12px);
		}
	}

</style>
