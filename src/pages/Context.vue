<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row">
		<div v-if="loading" class="icon-loading" />

		<div v-else-if="activeContext">
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
		</div>

		<ErrorMessage v-else-if="errorMessage" :message="errorMessage" />

		<MainModals />
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
import ErrorMessage from '../modules/main/partials/ErrorMessage.vue'
import displayError, { getNotFoundError, getGenericLoadError } from '../shared/utils/displayError.js'

export default {
	components: {
		MainModals,
		NcIconSvgWrapper,
		ErrorMessage,
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
			errorMessage: null,
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
		async activeContext() {
			if (this.errorMessage) {
				// Already showing an error, don't redirect
				return
			}
			await this.reload()
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
			this.contextResources = []

			try {
				await this.loadContext({ id: this.activeContextId })
				const index = this.contexts.findIndex(c => parseInt(c.id) === parseInt(this.activeContextId))
				this.context = this.contexts[index]

				if (!this.context) {
					this.errorMessage = t('tables', 'This application could not be found')
					return
				}

				this.icon = await this.getContextIcon(this.activeContext.iconName)

				if (this.context && this.context.nodes) {
					for (const [, node] of Object.entries(this.context.nodes)) {
						try {
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
						} catch (err) {
							console.error(`Failed to load resource ${node.node_id}:`, err)
							this.errorMessage = t('tables', 'Some resources in this application could not be loaded')
						}
					}
				}
			} catch (e) {
				if (e.message === 'NOT_FOUND') {
					this.errorMessage = getNotFoundError('application')
				} else {
					this.errorMessage = getGenericLoadError('application')
					displayError(e, this.errorMessage)
				}
			} finally {
				this.loading = false
			}
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
		margin-inline-start: 32px;
	}

	&:deep(.icon-vue) {
		min-width: 32px;
		min-height: 32px;
	}
}

.resource {
	margin: 40px 0;

	&:deep(.row.first-row) {
		margin-inline-start: 20px;
		padding-inline-start: 0px;
	}
}

:deep(h1) {
	font-size: unset;
	font-size: revert;
}
</style>
