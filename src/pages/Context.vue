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
					<div class="context__edit-actions">
						<template v-if="isEditingLayout">
							<NcButton v-if="hasCustomLayout" variant="tertiary" data-cy="context-layout-reset" @click="draftLayout = {}">
								<template #icon>
									<Restore :size="20" />
								</template>
								{{ t('tables', 'Reset layout') }}
							</NcButton>
							<NcButton variant="tertiary" data-cy="context-layout-cancel" @click="draftLayout = null">
								{{ t('tables', 'Cancel') }}
							</NcButton>
							<NcButton variant="primary" data-cy="context-layout-save" @click="saveLayout">
								<template #icon>
									<ContentSaveOutline :size="20" />
								</template>
								{{ t('tables', 'Save') }}
							</NcButton>
						</template>
						<NcButton v-else variant="secondary" data-cy="context-layout-edit" @click="draftLayout = { ...layout }">
							<template #icon>
								<PencilOutline :size="20" />
							</template>
							{{ t('tables', 'Edit layout') }}
						</NcButton>
					</div>
				</div>
				<div class="row space-L context__description">
					{{ activeContext.description }}
				</div>
			</div>

			<div class="context__grid" :class="{ 'context__grid--editing': isEditingLayout }">
				<section v-for="resource in contextResources"
					:key="resource.key"
					class="context__grid-item"
					:style="getGridCellStyle(getResourceSpan(resource))"
					data-cy="context-grid-item">
					<div v-if="isEditingLayout" class="context__grid-item-toolbar">
						<NcActions :menu-name="t('tables', 'Width')" :aria-label="t('tables', 'Width of {title}', { title: resource.title })">
							<template #icon>
								<TableColumnWidth :size="20" />
							</template>
							<NcActionRadio v-for="option in widthOptions"
								:key="option.span"
								:name="'context-width-' + resource.key"
								:value="option.span"
								:model-value="getResourceSpan(resource)"
								@update:model-value="span => setResourceSpan(resource, span)">
								{{ option.label }}
							</NcActionRadio>
						</NcActions>
					</div>
					<div v-if="!resource.isView" class="resource">
						<TableWrapper :table="resource" :columns="columns[resource.key]" :rows="rows[resource.key]"
							:view-setting="viewSetting" @create-column="createColumn(false, resource)"
							@import-scheme="openImportSchemeModal(resource)"
							@import="openImportModal(resource, false)" @download-csv="downloadCSV(resource, false)"
							@download-filtered-csv="rows => downloadFilteredCSV(rows, resource, false)" />
					</div>
					<div v-else-if="resource.isView" class="resource">
						<CustomView :view="resource" :columns="columns[resource.key]" :rows="rows[resource.key]"
							:view-setting="viewSetting" @create-column="createColumn(true, resource)"
							@import="openImportModal(resource, true)" @download-csv="downloadCSV(resource, true)"
							@download-filtered-csv="rows => downloadFilteredCSV(rows, resource, true)" />
					</div>
				</section>
			</div>
		</div>

		<ErrorMessage v-else-if="errorMessage" :message="errorMessage" />

		<MainModals />
	</div>
</template>

<script>
import MainModals from '../modules/modals/Modals.vue'
import { mapState, mapActions, storeToRefs } from 'pinia'
import { NcActionRadio, NcActions, NcButton, NcIconSvgWrapper } from '@nextcloud/vue'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import Restore from 'vue-material-design-icons/Restore.vue'
import TableColumnWidth from 'vue-material-design-icons/TableColumnWidth.vue'
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
import { GRID_COLUMNS, getGridCellStyle, loadContextLayout, saveContextLayout } from '../shared/utils/contextLayout.js'

export default {
	components: {
		MainModals,
		NcActionRadio,
		NcActions,
		NcButton,
		NcIconSvgWrapper,
		ContentSaveOutline,
		PencilOutline,
		Restore,
		TableColumnWidth,
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
			layout: {},
			draftLayout: null,
		}
	},

	computed: {
		...mapState(useTablesStore, ['tables', 'contexts', 'activeContextId', 'views', 'activeContext']),
		isEditingLayout() {
			return this.draftLayout !== null
		},
		activeLayout() {
			return this.draftLayout ?? this.layout
		},
		hasCustomLayout() {
			return Object.keys(this.activeLayout).length > 0
		},
		widthOptions() {
			return [
				{ span: 12, label: t('tables', 'Full width') },
				{ span: 8, label: t('tables', 'Two thirds') },
				{ span: 6, label: t('tables', 'Half') },
				{ span: 4, label: t('tables', 'One third') },
			]
		},
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
		...mapActions(useDataStore, ['loadColumnsFromBE', 'loadRowsFromBE', 'loadRelationsFromBE']),
		getGridCellStyle,
		getResourceSpan(resource) {
			return this.activeLayout[resource.key] ?? GRID_COLUMNS
		},
		setResourceSpan(resource, span) {
			const draftLayout = { ...this.activeLayout }
			if (span === GRID_COLUMNS) {
				delete draftLayout[resource.key]
			} else {
				draftLayout[resource.key] = span
			}
			this.draftLayout = draftLayout
		},
		saveLayout() {
			if (this.draftLayout === null) {
				return
			}
			// The layout is the user's own arrangement and saves once, when editing ends
			this.layout = this.draftLayout
			this.draftLayout = null
			saveContextLayout(this.activeContextId, this.layout)
		},
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
			this.layout = loadContextLayout(this.activeContextId)
			this.draftLayout = null

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
										await this.loadRelationsFromBE({
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
										await this.loadRelationsFromBE({
											viewId: view.id,
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
		openImportSchemeModal(table) {
			emit('tables:modal:table-scheme-import', { table })
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

	&__edit-actions {
		display: flex;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline, 4px));
		margin-inline-start: auto;
		padding-inline-end: calc(4 * var(--default-grid-baseline, 4px));
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
	width: 100%;
}

// 12-column application grid, collapsing to 6 columns and then to one on smaller screens
.context__grid {
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: calc(4 * var(--default-grid-baseline, 4px));
	padding: calc(4 * var(--default-grid-baseline, 4px));
}

.context__grid-item {
	grid-column: span var(--context-grid-span, 12);
	min-width: 0;
	overflow-x: auto;
	background-color: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	box-shadow: 0 2px 4px var(--color-box-shadow);
}

.context__grid--editing .context__grid-item {
	outline: 2px dashed var(--color-border-dark);
	outline-offset: 2px;
}

.context__grid-item-toolbar {
	display: flex;
	justify-content: flex-end;
	padding: calc(2 * var(--default-grid-baseline, 4px));
	border-bottom: 1px solid var(--color-border);
}

@media (max-width: 900px) {
	.context__grid {
		grid-template-columns: repeat(6, minmax(0, 1fr));
	}

	.context__grid-item {
		grid-column: span var(--context-grid-span-medium, 6);
	}
}

@media (max-width: 600px) {
	.context__grid {
		grid-template-columns: minmax(0, 1fr);
	}

	.context__grid-item {
		grid-column: 1 / -1;
	}
}

.resource {
	// The title and option rows stick to the page width on a table page; inside a grid cell they flow with the card
	&:deep(.row.first-row),
	&:deep(.row.space-T),
	&:deep(.options.row),
	&:deep(.options .sticky) {
		position: static;
		width: auto;
	}

	&:deep(.row.first-row) {
		margin-inline-start: 0;
		padding-inline-start: calc(4 * var(--default-grid-baseline, 4px));
	}

	// Table look shared with OpenRegister: muted header band and horizontal row lines only
	&:deep(table thead) {
		top: 0;
	}

	&:deep(table thead tr th) {
		background-color: var(--color-background-dark);
		font-weight: 500;
	}

	&:deep(table tbody td) {
		border: none;
		border-bottom: 1px solid var(--color-border);
		padding-block: calc(3 * var(--default-grid-baseline, 4px));
	}
}

:deep(h1) {
	font-size: unset;
	font-size: revert;
}
</style>
