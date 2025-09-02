<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row">
		<div v-if="loading" class="icon-loading" />
		<div v-if="!loading && context">
			<div class="content context">
				<div class="row first-row">
					<h1 class="context__title" data-cy="context-title">
						<NcIconSvgWrapper :svg="icon" :size="32" style="display: inline-block;" />&nbsp; {{
							activeContext.name }}
					</h1>
				</div>
				<div class="row space-L context__description">
					{{ activeContext.description }}
				</div>
			</div>

			<div class="resources">
				<div v-for="resource in contextResources" :key="resource.key">
					<div v-if="!resource.isView" class="resource">
						<TableWrapper :table="resource" :columns="columns[resource.key]" :rows="rows[resource.key]"
							:view-setting="viewSetting" @create-column="createColumn(false, resource)"
							@import="openImportModal(resource, false)" @download-csv="downloadCSV(resource, false)" />
					</div>
					<div v-else-if="resource.isView" class="resource">
						<CustomView :view="resource" :columns="columns[resource.key]" :rows="rows[resource.key]"
							:view-setting="viewSetting" @create-column="createColumn(true, resource)"
							@import="openImportModal(resource, true)" @download-csv="downloadCSV(resource, true)" />
					</div>
				</div>
			</div>

			<MainModals />
		</div>
	</div>
</template>

<script>
import MainModals from '../modules/modals/Modals.vue'
import { mapState, mapActions, storeToRefs } from 'pinia'
import { NcIconSvgWrapper } from '@nextcloud/vue'
import TableWrapper from '../modules/main/sections/TableWrapper.vue'
import CustomView from '../modules/main/sections/View.vue'
import { emit } from '@nextcloud/event-bus'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../shared/constants.ts'
import exportTableMixin from '../shared/components/ncTable/mixins/exportTableMixin.js'
import svgHelper from '../shared/components/ncIconPicker/mixins/svgHelper.js'
import { useTablesStore } from '../store/store.js'
import { useDataStore } from '../store/data.js'

export default {
	components: {
		MainModals,
		NcIconSvgWrapper,
		TableWrapper,
		CustomView,
	},

	mixins: [exportTableMixin, svgHelper],
	setup() {
		const store = useDataStore()
		const { getColumns, getRows } = storeToRefs(store)
		return { getColumns, getRows }
	},

	data() {
		return {
			loading: true,
			icon: null,
			viewSetting: {},
			context: null,
			contextResources: [],
		}
	},

	computed: {
		...mapState(useTablesStore, ['tables', 'contexts', 'activeContextId', 'views', 'activeContext']),
		rows() {
			const rows = {}
			if (this.context && this.context.nodes) {
				for (const [, node] of Object.entries(this.context.nodes)) {
					if (parseInt(node.node_type) === NODE_TYPE_TABLE) {
						const rowId = this.getKey(false, node.node_id)
						rows[rowId] = this.getRows(false, node.node_id)
					} else if (parseInt(node.node_type) === NODE_TYPE_VIEW) {
						const rowId = this.getKey(true, node.node_id)
						rows[rowId] = this.getRows(true, node.node_id)
					}
				}
			}
			return rows

		},

		columns() {
			const columns = {}
			if (this.context && this.context.nodes) {
				for (const [, node] of Object.entries(this.context.nodes)) {
					if (parseInt(node.node_type) === NODE_TYPE_TABLE) {
						const columnId = this.getKey(false, node.node_id)
						columns[columnId] = this.getColumns(false, node.node_id)
					} else if (parseInt(node.node_type) === NODE_TYPE_VIEW) {
						const columnId = this.getKey(true, node.node_id)
						columns[columnId] = this.getColumns(true, node.node_id)
					}

				}
			}
			return columns
		},
	},

	watch: {
		// Watch for changes to active context to make page reactive
		async activeContext() {
			if (this.activeContextId && !this.activeContext) {
				// context does not exists, go to startpage
				this.$router.push('/').catch(err => err)
			} else {
				await this.reload()
			}
		},
		'context.iconName': {
			async handler(value) {
				this.icon = value ? await this.getContextIcon(value) : ''
			},
			immediate: true,
		},
	},

	async mounted() {
		emit('toggle-navigation', {
			open: false,
		})
		await this.reload()
	},

	methods: {
		...mapActions(useTablesStore, ['loadContext']),
		...mapActions(useDataStore, ['loadColumnsFromBE', 'loadRowsFromBE']),
		async reload() {
			if (!this.activeContextId) {
				return
			}
			this.loading = true
			this.icon = await this.getContextIcon(this.activeContext.iconName)
			this.contextResources = []
			await this.loadContext({ id: this.activeContextId })
			const index = this.contexts.findIndex(c => parseInt(c.id) === parseInt(this.activeContextId))
			this.context = this.contexts[index]

			if (this.context && this.context.nodes) {
				for (const [, node] of Object.entries(this.context.nodes)) {
					const nodeType = parseInt(node.node_type)
					if (nodeType === NODE_TYPE_TABLE) {
						const table = this.tables.find(table => table.id === node.node_id)
						if (table) {
							await this.loadColumnsFromBE({
								view: null,
								tableId: table.id,
							})
							await this.loadRowsFromBE({
								viewId: null,
								tableId: table.id,
							})
							table.key = (table.id).toString()
							table.isView = false
							this.contextResources.push(table)
						}

					} else if (nodeType === NODE_TYPE_VIEW) {
						const view = this.views.find(view => view.id === node.node_id)
						if (view) {
							await this.loadColumnsFromBE({
								view,
							})
							await this.loadRowsFromBE({
								viewId: view.id,
								tableId: view.tableId,
							})
							view.key = 'view-' + (view.id).toString()
							view.isView = true
							this.contextResources.push(view)
						}
					}
				}
			}
			this.loading = false
		},
		createColumn(isView, element) {
			emit('tables:column:create', { isView, element })
		},
		downloadCSV(element, isView) {
			const rowId = this.getKey(isView, element.key)
			const colId = this.getKey(isView, element.key)
			this.downloadCsv(this.rows[rowId], this.columns[colId], element.title)
		},
		getKey(isView, id) {
			return isView ? 'view-' + id : id
		},
		openImportModal(element, isView) {
			emit('tables:modal:import', { element, isView })
		},
	},
}

</script>

<style scoped lang="scss">
.context {
	&__title {
		display: inline-flex;
	}

	&__description {
		margin: calc(3 * var(--default-grid-baseline, 4px));
		max-width: 790px;
		margin-left: 32px;
	}

	&:deep(.icon-vue) {
		min-width: 32px;
		min-height: 32px;
	}
}

.resource {
	margin: 40px 0;

	&:deep(.row.first-row) {
		margin-left: 20px;
		padding-left: 0px;
	}
}
:deep(h1) {
	font-size: unset;
	font-size: revert;
}
</style>
