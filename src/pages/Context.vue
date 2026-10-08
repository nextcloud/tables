<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row main-context-view">
		<div v-if="loading" class="icon-loading" />

		<div v-else-if="activeContext" :class="{ 'context--standalone': isStandalone }">
			<div v-if="!isStandalone" class="content context">
				<div class="row first-row">
					<h1 class="context__title" data-cy="context-title">
						<NcIconSvgWrapper :svg="icon" :size="32" style="display: inline-block;" />&nbsp; {{
							activeContext.name }}
					</h1>
					<div class="context__edit-actions">
						<template v-if="!isEditingLayout">
							<NcButton variant="primary" :href="applicationUrl" target="_blank" rel="noopener" data-cy="context-open-application">
								<template #icon>
									<OpenInNew :size="20" />
								</template>
								{{ t('tables', 'Open application') }}
							</NcButton>
							<NcButton v-if="ownsContext(activeContext)" variant="secondary" data-cy="context-add-grid-view" @click="addGridView">
								<template #icon>
									<ViewDashboardOutline :size="20" />
								</template>
								{{ t('tables', 'Add page') }}
							</NcButton>
							<NcButton v-if="ownsContext(activeContext)" variant="secondary" data-cy="context-edit-application" @click="editApplication">
								<template #icon>
									<PlaylistEdit :size="20" />
								</template>
								{{ t('tables', 'Edit application') }}
							</NcButton>
						</template>
					</div>
				</div>
				<div class="row space-L context__description">
					{{ activeContext.description }}
					<span class="context__address" data-cy="context-address">{{ applicationUrl }}</span>
				</div>
				<nav v-if="menuItems.length > 0" class="context__menu" :aria-label="t('tables', 'Application menu')" data-cy="context-menu">
					<router-link :to="'/application/' + activeContext.id"
						class="context__menu-item"
						:class="{ 'context__menu-item--active': !selectedMenuItem }"
						data-cy="context-menu-overview">
						{{ t('tables', 'Overview') }}
					</router-link>
					<template v-for="item in menuItems" :key="item.id">
						<a v-if="item.targetType === 'url'"
							:href="item.url"
							class="context__menu-item"
							target="_blank"
							rel="noopener noreferrer"
							data-cy="context-menu-item">
							{{ item.label }}
							<OpenInNew :size="16" />
						</a>
						<router-link v-else
							:to="menuItemRoute(item, activeContext.id)"
							class="context__menu-item"
							:class="{ 'context__menu-item--active': selectedMenuItem && selectedMenuItem.id === item.id }"
							data-cy="context-menu-item">
							{{ item.label }}
						</router-link>
					</template>
				</nav>
			</div>

			<div v-if="selectedMenuItem" class="context__menu-target" data-cy="context-menu-target">
				<div v-if="menuTargetLoading" class="icon-loading" />
				<NcEmptyContent v-else-if="!menuResource"
					:name="t('tables', 'This menu item points at nothing')"
					:description="t('tables', 'The view or table it opened no longer exists, or you have no access to it.')" />
				<GridView v-else-if="menuResource.isView && menuResource.type === 'grid'"
					:view="menuResource"
					:can-edit="canManageElement(menuResource)"
					:show-title="true" />
				<div v-else-if="menuResource.isView" class="resource">
					<CustomView :view="menuResource" :columns="getColumns(true, menuResource.id)" :rows="getRows(true, menuResource.id)"
						:view-setting="viewSetting" @create-column="createColumn(true, menuResource)"
						@import="openImportModal(menuResource, true)" @download-csv="downloadCSV(menuResource, true)"
						@download-filtered-csv="rows => downloadFilteredCSV(rows, menuResource, true)" />
				</div>
				<div v-else class="resource">
					<TableWrapper :table="menuResource" :columns="getColumns(false, menuResource.id)" :rows="getRows(false, menuResource.id)"
						:view-setting="viewSetting" @create-column="createColumn(false, menuResource)"
						@import-scheme="openImportSchemeModal(menuResource)"
						@import="openImportModal(menuResource, false)" @download-csv="downloadCSV(menuResource, false)"
						@download-filtered-csv="rows => downloadFilteredCSV(rows, menuResource, false)" />
				</div>
			</div>

			<template v-if="!selectedMenuItem && !isStandalone">
				<section class="context__section" data-cy="context-pages">
					<div class="context__section-header">
						<h2>{{ t('tables', 'Pages') }}</h2>
						<NcButton v-if="ownsContext(activeContext)" variant="secondary" data-cy="context-add-page" @click="addGridView">
							<template #icon>
								<Plus :size="20" />
							</template>
							{{ t('tables', 'Add page') }}
						</NcButton>
					</div>
					<p v-if="pageCards.length === 0" class="context__hint">
						{{ t('tables', 'No pages yet. A page is a view with its own entry in the application menu.') }}
					</p>
					<ul v-else class="context__cards">
						<li v-for="card in pageCards" :key="card.item.id" class="page-card" data-cy="page-card">
							<div class="page-card__icon">
								<ViewDashboardOutline v-if="card.kind === 'grid'" :size="28" />
								<TableIcon v-else-if="card.kind === 'table' || card.kind === 'view'" :size="28" />
								<OpenInNew v-else :size="28" />
							</div>
							<div class="page-card__text">
								<h3 class="page-card__title">
									{{ card.item.label }}
								</h3>
								<p class="page-card__meta">
									{{ card.description }}
								</p>
							</div>
							<div class="page-card__actions">
								<NcButton v-if="card.route" variant="secondary" :to="card.route" data-cy="page-card-open">
									{{ card.kind === 'grid' ? t('tables', 'Design') : t('tables', 'Open') }}
								</NcButton>
								<NcButton v-else variant="secondary" :href="card.item.url" target="_blank" rel="noopener noreferrer">
									{{ t('tables', 'Open') }}
								</NcButton>
							</div>
						</li>
					</ul>
				</section>

				<section v-if="ownsContext(activeContext)" class="context__section" data-cy="context-menu-editor">
					<div class="context__section-header">
						<h2>{{ t('tables', 'Menu') }}</h2>
						<div class="context__section-actions">
							<NcButton v-if="menuDraft !== null" variant="tertiary" data-cy="context-menu-cancel" @click="menuDraft = null">
								{{ t('tables', 'Cancel') }}
							</NcButton>
							<NcButton v-if="menuDraft !== null" variant="primary" :disabled="menuSaving" data-cy="context-menu-save" @click="saveMenu">
								<template #icon>
									<ContentSaveOutline :size="20" />
								</template>
								{{ t('tables', 'Save menu') }}
							</NcButton>
							<NcButton v-else variant="secondary" data-cy="context-menu-edit" @click="menuDraft = toEditableMenuItems(menuItems)">
								<template #icon>
									<PencilOutline :size="20" />
								</template>
								{{ t('tables', 'Edit menu') }}
							</NcButton>
						</div>
					</div>
					<MenuItemsEditor v-if="menuDraft !== null" v-model:items="menuDraft" />
					<ol v-else-if="menuItems.length > 0" class="context__menu-preview" data-cy="context-menu-preview">
						<li v-for="item in menuItems" :key="item.id">
							{{ item.label }}
						</li>
					</ol>
					<p v-else class="context__hint">
						{{ t('tables', 'The menu is empty. Add a page, or edit the menu to link tables and external links.') }}
					</p>
				</section>
			</template>

			<div v-if="!selectedMenuItem && isStandalone" class="context__layout-toolbar" data-cy="context-layout-toolbar">
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
			<div v-if="!selectedMenuItem && isStandalone" class="context__grid" :class="{ 'context__grid--editing': isEditingLayout }">
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
import { NcActionRadio, NcActions, NcButton, NcEmptyContent, NcIconSvgWrapper } from '@nextcloud/vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import TableIcon from 'vue-material-design-icons/Table.vue'
import MenuItemsEditor from '../shared/components/ncContextResource/MenuItemsEditor.vue'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import GridView from '../modules/grid/GridView.vue'
import permissionsMixin from '../shared/components/ncTable/mixins/permissionsMixin.js'
import { useEventBusSubscriptions } from '../shared/composables/useEventBusSubscriptions.js'
import { menuItemRoute, toEditableMenuItems, toMenuItemPayload } from '../shared/utils/menuItems.js'
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import { GRID_COLUMNS, getGridCellStyle, loadContextLayout, saveContextLayout } from '../shared/utils/contextLayout.js'

export default {
	components: {
		MainModals,
		NcActionRadio,
		NcActions,
		NcButton,
		NcEmptyContent,
		NcIconSvgWrapper,
		OpenInNew,
		PlaylistEdit,
		Plus,
		TableIcon,
		MenuItemsEditor,
		ViewDashboardOutline,
		GridView,
		ContentSaveOutline,
		PencilOutline,
		Restore,
		TableColumnWidth,
		ErrorMessage,
		TableWrapper,
		CustomView,
	},

	mixins: [exportTableMixin, svgHelper, permissionsMixin],

	setup() {
		const store = useDataStore()
		const { getColumns, getRows } = storeToRefs(store)
		return { getColumns, getRows, ...useEventBusSubscriptions() }
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
			menuResource: null,
			menuTargetLoading: false,
			menuDraft: null,
			menuSaving: false,
			standaloneContextId: loadState('tables', 'contextId', null),
			openedFirstPage: false,
		}
	},

	computed: {
		...mapState(useTablesStore, ['tables', 'contexts', 'activeContextId', 'views', 'activeContext']),
		isEditingLayout() {
			return this.draftLayout !== null
		},
		menuItems() {
			return [...(this.activeContext?.menuItems ?? [])].sort((a, b) => a.order - b.order)
		},
		isStandalone() {
			return this.standaloneContextId !== null
		},
		applicationUrl() {
			return window.location.origin + generateUrl('/apps/tables/app/' + (this.activeContext?.technicalName || this.activeContext?.id))
		},
		pageCards() {
			return this.menuItems.map(item => {
				if (item.targetType === 'url') {
					return { item, kind: 'url', route: null, description: item.url }
				}
				if (item.targetType === 'table') {
					const table = this.tables.find(table => table.id === item.targetId)
					return { item, kind: 'table', route: menuItemRoute(item, this.activeContext.id), description: table ? t('tables', 'Table: {title}', { title: table.title }) : t('tables', 'Table') }
				}
				const view = this.views.find(view => view.id === item.targetId)
				const kind = view?.type === 'grid' ? 'grid' : 'view'
				const description = kind === 'grid'
					? n('tables', '%n widget', '%n widgets', view.grid?.widgets?.length ?? 0)
					: (view ? t('tables', 'View: {title}', { title: view.title }) : t('tables', 'View'))
				return { item, kind, route: menuItemRoute(item, this.activeContext.id), description }
			})
		},
		selectedMenuItem() {
			const technicalName = this.$route.params.itemName
			if (!technicalName) {
				return null
			}
			return this.menuItems.find(item => item.technicalName === technicalName) ?? null
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
		selectedMenuItem: {
			immediate: true,
			handler() {
				this.loadMenuTarget()
				this.openFirstPageWhenStandalone()
			},
		},
		menuItems() {
			this.openFirstPageWhenStandalone()
		},

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
		// inside Tables the application page wants the room; opened on its own, the navigation is the menu
		if (!this.isStandalone) {
			emit('toggle-navigation', {
				open: false,
			})
		}
		this.subscribeToEventBus('tables:view:grid-created', this.onGridViewCreated)
		await this.reload()
	},

	methods: {
		...mapActions(useTablesStore, ['loadContext', 'validateExportAccess', 'loadContextTable', 'loadContextView', 'updateContextMenuItems']),
		...mapActions(useDataStore, ['loadColumnsFromBE', 'loadRowsFromBE', 'loadRelationsFromBE']),
		getGridCellStyle,
		menuItemRoute,
		editApplication() {
			emit('tables:context:edit', this.activeContext.id)
		},
		toEditableMenuItems,
		/**
		 * An application opened on its own starts on its first page, not on the resource overview.
		 * Only once: the overview stays reachable from the menu afterwards.
		 */
		openFirstPageWhenStandalone() {
			if (!this.isStandalone || this.openedFirstPage || this.$route.params.itemName || this.menuItems.length === 0) {
				return
			}
			const first = this.menuItems.find(item => item.targetType !== 'url')
			if (first) {
				this.openedFirstPage = true
				this.$router.replace(menuItemRoute(first, this.activeContext.id)).catch(err => err)
			}
		},
		async saveMenu() {
			if (this.menuDraft === null) {
				return
			}
			this.menuSaving = true
			const context = await this.updateContextMenuItems({ id: this.activeContext.id, menuItems: this.menuDraft.map(toMenuItemPayload) })
			this.menuSaving = false
			if (context) {
				this.menuDraft = null
				showSuccess(t('tables', 'Menu saved'))
			}
		},
		addGridView() {
			emit('tables:view:create-grid', { contextId: this.activeContext.id })
		},
		/**
		 * A grid view created from this application gets a menu item right away.
		 *
		 * @param {{view: object, contextId: number|null, technicalName?: string|null}} payload the created view, the application it was created from and the name typed for it
		 */
		async onGridViewCreated({ view, contextId, technicalName = null }) {
			if (!view || contextId !== this.activeContext?.id) {
				return
			}
			const menuItems = [
				...this.menuItems.map(item => ({ label: item.label, icon: item.icon, targetType: item.targetType, targetId: item.targetId, url: item.url, technicalName: item.technicalName })),
				{ label: view.title, icon: null, targetType: 'view', targetId: view.id, url: null, technicalName },
			]
			const context = await this.updateContextMenuItems({ id: this.activeContext.id, menuItems })
			const added = context?.menuItems?.find(item => item.targetType === 'view' && item.targetId === view.id)
			if (added) {
				await this.$router.push(menuItemRoute(added, this.activeContext.id)).catch(err => err)
			}
		},
		/**
		 * Loads the view or table the selected menu item opens.
		 */
		async loadMenuTarget() {
			const item = this.selectedMenuItem
			this.menuResource = null
			if (!item || item.targetType === 'url') {
				return
			}
			this.menuTargetLoading = true
			try {
				if (item.targetType === 'view') {
					await this.loadContextView({ id: item.targetId })
					const view = this.views.find(view => view.id === item.targetId)
					if (view) {
						if (view.type !== 'grid') {
							await this.loadColumnsFromBE({ view })
							await this.loadRowsFromBE({ viewId: view.id, tableId: view.tableId })
							await this.loadRelationsFromBE({ viewId: view.id })
						}
						this.menuResource = { ...view, isView: true, key: 'view-' + view.id }
					}
				} else {
					await this.loadContextTable({ id: item.targetId })
					const table = this.tables.find(table => table.id === item.targetId)
					if (table) {
						await this.loadColumnsFromBE({ view: null, tableId: table.id })
						await this.loadRowsFromBE({ viewId: null, tableId: table.id })
						await this.loadRelationsFromBE({ tableId: table.id })
						this.menuResource = { ...table, isView: false, key: String(table.id) }
					}
				}
			} catch (error) {
				console.error('The menu target could not be loaded', error)
				this.menuResource = null
			} finally {
				this.menuTargetLoading = false
			}
		},
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
		flex-wrap: wrap;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline, 4px));
		margin-inline-start: auto;
		padding-inline-end: calc(4 * var(--default-grid-baseline, 4px));
	}

	// on a narrow screen the actions drop below the title instead of pushing it off screen
	.row.first-row {
		flex-wrap: wrap;
		row-gap: calc(2 * var(--default-grid-baseline, 4px));
	}

	&__menu {
		display: flex;
		flex-wrap: wrap;
		gap: calc(2 * var(--default-grid-baseline, 4px));
		padding: 0 calc(4 * var(--default-grid-baseline, 4px)) calc(2 * var(--default-grid-baseline, 4px));
		border-bottom: 1px solid var(--color-border);
	}

	&__menu-item {
		display: inline-flex;
		align-items: center;
		gap: calc(1 * var(--default-grid-baseline, 4px));
		padding: calc(2 * var(--default-grid-baseline, 4px)) calc(3 * var(--default-grid-baseline, 4px));
		border-radius: var(--border-radius-element, var(--border-radius-pill));
		color: inherit;
		text-decoration: none;
		font-weight: bold;

		&:hover,
		&:focus-visible {
			background-color: var(--color-background-hover);
		}

		&--active {
			background-color: var(--color-primary-element-light);
			color: var(--color-primary-element-light-text);
		}
	}

	&__address {
		display: block;
		margin-top: calc(1 * var(--default-grid-baseline, 4px));
		color: var(--color-text-maxcontrast);
		font-family: monospace;
		font-size: 0.9em;
	}

	&__section {
		padding: calc(2 * var(--default-grid-baseline, 4px)) calc(4 * var(--default-grid-baseline, 4px));
	}

	&__section-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: calc(2 * var(--default-grid-baseline, 4px));

		h2 {
			margin: 0;
			font-size: 18px;
			font-weight: bold;
		}
	}

	&__section-actions {
		display: flex;
		gap: calc(2 * var(--default-grid-baseline, 4px));
	}

	&__hint {
		color: var(--color-text-maxcontrast);
	}

	&__cards {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
		gap: calc(3 * var(--default-grid-baseline, 4px));
		margin-top: calc(2 * var(--default-grid-baseline, 4px));
		list-style: none;
	}

	&__menu-preview {
		margin: calc(2 * var(--default-grid-baseline, 4px)) 0 0 calc(5 * var(--default-grid-baseline, 4px));
	}

	&__layout-toolbar {
		display: flex;
		justify-content: flex-end;
		gap: calc(2 * var(--default-grid-baseline, 4px));
		padding: calc(3 * var(--default-grid-baseline, 4px)) calc(4 * var(--default-grid-baseline, 4px)) 0 calc(var(--default-clickable-area, 44px) + calc(4 * var(--default-grid-baseline, 4px)));
	}

	&__menu-target {
		width: 100%;
		padding: calc(2 * var(--default-grid-baseline, 4px)) 0;

		.resource {
			overflow-x: auto;
		}
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

.page-card {
	display: flex;
	align-items: center;
	gap: calc(3 * var(--default-grid-baseline, 4px));
	padding: calc(3 * var(--default-grid-baseline, 4px));
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);

	&__icon {
		flex-shrink: 0;
		color: var(--color-primary-element);
	}

	&__text {
		flex: 1;
		min-width: 0;
	}

	&__title {
		margin: 0;
		font-size: var(--default-font-size);
		font-weight: bold;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__meta {
		margin: 0;
		color: var(--color-text-maxcontrast);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__actions {
		flex-shrink: 0;
	}
}

.context--standalone {
	width: 100%;

	// leave room for the navigation toggle that floats over the top left corner of the content
	.context__menu-target {
		padding-inline-start: var(--default-clickable-area, 44px);
	}
}
</style>
