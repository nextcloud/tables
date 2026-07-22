<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row main-context-view">
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
							@import="openImportModal(resource, false)" @download-csv="downloadCSV(resource, false)"
							@download-filtered-csv="rows => downloadFilteredCSV(rows, resource, false)" />
					</div>
					<div v-else-if="resource.isView" class="resource">
						<CustomView :view="resource" :columns="columns[resource.key]" :rows="rows[resource.key]"
							:view-setting="viewSetting" @create-column="createColumn(true, resource)"
							@import="openImportModal(resource, true)" @download-csv="downloadCSV(resource, true)"
							@download-filtered-csv="rows => downloadFilteredCSV(rows, resource, true)" />
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
import { showError } from '@nextcloud/dialogs'

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
			loadedSignature: null,
			isReloading: false,
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

		activeContext: {
			handler() {
				if (this.errorMessage) {
					// Already showing an error, don't redirect
					return
				}
				const signature = this.contextSignature()
				if (signature === this.loadedSignature) {
					return
				}
				this.loadedSignature = signature
				this.reload()
			},
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
		...mapActions(useTablesStore, ['loadContext', 'validateExportAccess', 'loadContextTable', 'loadContextView']),
		...mapActions(useDataStore, ['loadColumnsFromBE', 'loadRowsFromBE']),
		contextSignature() {
			const ctx = this.activeContext
			return ctx ? `${ctx.id}:${Object.keys(ctx.nodes || {}).sort().join(',')}` : null
		},
		async reload() {
			if (!this.activeContextId) {
				return
			}

			if (this.isReloading) {
				return
			}
			this.isReloading = true
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

				this.loadedSignature = this.contextSignature()

				this.icon = await this.getContextIcon(this.activeContext.iconName)

				this.icon = await this.getContextIcon(this.activeContext.iconName)

				if (this.context && this.context.pages) {
					const pages = Object.values(this.context.pages)
					const startPage = pages.find(p => p.page_type === 'startpage')

					if (startPage && startPage.content) {
						const sortedContent = Object.values(startPage.content).sort((a, b) => a.order - b.order)

						for (const content of sortedContent) {
							const node = this.context.nodes[content.node_rel_id]
							if (!node) continue

							try {
								const nodeType = parseInt(node.node_type)
								if (nodeType === NODE_TYPE_TABLE) {

									await this.loadContextTable({ id: node.node_id })
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
									await this.loadContextView({ id: node.node_id })
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
					// TODO: check this
					// Fallback if no startpage or content (though unexpected for valid contexts with nodes)
					// If nodes exist but not in startpage content, they won't be shown.
					// This matches backend logic where nodes are added to startpage.
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
				this.isReloading = false
			}
		},
		createColumn(isView, element) {
			emit('tables:column:create', { isView, element })
		},
		async downloadCSV(element, isView) {
			const access = await this.validateExportAccess({
				id: element.id,
				isView,
			})

			if (!access?.ok) {
				if (access?.reason === 'NO_ACCESS') {
					showError(t('tables', 'Your access was revoked. Reload the page to update your permissions.'))
				}
				return
			}

			const rowId = this.getKey(isView, element.id)
			const colId = this.getKey(isView, element.id)
			this.downloadCsv(this.rows[rowId], this.columns[colId], element.title)
		},
		async downloadFilteredCSV(rows, element, isView) {
			const access = await this.validateExportAccess({
				id: element.id,
				isView,
			})

			if (!access?.ok) {
				if (access?.reason === 'NO_ACCESS') {
					showError(t('tables', 'Your access was revoked. Reload the page to update your permissions.'))
				}
				return
			}

			const colId = this.getKey(isView, element.id)
			this.downloadCsv(rows, this.columns[colId], element.title)
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
	.row.first-row {
		position: sticky;
		inset-inline-start: 0;
		top: 0;
		z-index: 15;
		background-color: var(--color-main-background);
		width: var(--app-content-width, 100%);
	}

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

.main-context-view {
	width: max-content;
	min-width: var(--app-content-width, 100%);
}

.resource {
	margin: 40px 0;
	width: max-content;
	min-width: var(--app-content-width, 100%);

	&:deep(.row.first-row) {
		margin-inline-start: 0;
		padding-inline-start: 20px;
	}
}

:deep(h1) {
	font-size: unset;
	font-size: revert;
}
</style>
