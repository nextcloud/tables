<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="grid-page" :data-cy="isStartPage ? 'startPageGrid' : 'gridPage'">
		<div v-if="showHeading || canEdit" class="grid-page__header">
			<div v-if="showHeading" class="grid-page__heading">
				<h2 class="grid-page__title" data-cy="gridPageTitle">
					<NamedIcon v-if="page.icon" :name="page.icon" :size="20" />
					{{ title }}
				</h2>
				<p v-if="page.description" class="grid-page__description">
					{{ page.description }}
				</p>
			</div>
			<div v-if="canEdit" class="grid-page__actions">
				<template v-if="isEditing">
					<NcButton variant="secondary" data-cy="gridPageAddWidget" @click="openAddWidget">
						<template #icon>
							<Plus :size="20" />
						</template>
						{{ t('tables', 'Add widget') }}
					</NcButton>
					<NcButton variant="tertiary" data-cy="gridPageCancel" @click="cancelEditing">
						{{ t('tables', 'Cancel') }}
					</NcButton>
					<NcButton variant="primary" :disabled="saving" data-cy="gridPageSave" @click="save">
						<template #icon>
							<Check :size="20" />
						</template>
						{{ t('tables', 'Save') }}
					</NcButton>
				</template>
				<template v-else>
					<NcButton variant="secondary" data-cy="gridPageEdit" @click="startEditing">
						<template #icon>
							<PencilOutline :size="20" />
						</template>
						{{ t('tables', 'Edit page') }}
					</NcButton>
					<NcActions :aria-label="t('tables', 'Page actions')" data-cy="gridPageActions">
						<NcActionButton :close-after-click="true" data-cy="gridPageEditDetails" @click="$emit('edit-details', page)">
							<template #icon>
								<TextBoxEditOutline :size="20" />
							</template>
							{{ t('tables', 'Edit page details') }}
						</NcActionButton>
						<NcActionButton v-if="!isStartPage"
							:close-after-click="true"
							data-cy="gridPageDelete"
							@click="$emit('delete', page)">
							<template #icon>
								<TrashCanOutline :size="20" />
							</template>
							{{ t('tables', 'Delete page') }}
						</NcActionButton>
					</NcActions>
				</template>
			</div>
		</div>

		<NcEmptyContent v-if="activeGrid.layout.length === 0"
			class="grid-page__empty"
			:name="t('tables', 'This page is empty')"
			:description="emptyDescription">
			<template #icon>
				<ViewDashboardOutline />
			</template>
			<template v-if="isEditing" #action>
				<NcButton variant="primary" @click="openAddWidget">
					{{ t('tables', 'Add widget') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<GridCanvas :layout="pageLayout" :editable="isEditing" @layout-change="onLayoutChange">
			<template #widget="{ item, narrow }">
				<GridWidget v-if="widgetsById[item.widgetId]"
					:widget="widgetsById[item.widgetId]"
					:nodes="context.nodes"
					:layout-item="item"
					:editable="isEditing"
					:arrangeable="!narrow"
					@configure="configureWidget"
					@remove="removeWidget"
					@move="moveWidget" />
			</template>
		</GridCanvas>

		<AddWidget :show-modal="addWidgetOpen"
			:editing-widget="widgetBeingConfigured"
			:nodes="context.nodes"
			:allow-tabs="targetTab === null"
			@close="closeAddWidget"
			@submit="onWidgetSubmit" />
	</div>
</template>

<script>
import { NcActionButton, NcActions, NcButton, NcEmptyContent } from '@nextcloud/vue'
import { showConfirmation, showSuccess } from '@nextcloud/dialogs'
import Check from 'vue-material-design-icons/Check.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import TextBoxEditOutline from 'vue-material-design-icons/TextBoxEditOutline.vue'
import TrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import { computed, ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import { mapActions, mapState } from 'pinia'
import GridCanvas from './GridCanvas.vue'
import GridWidget from './GridWidget.vue'
import AddWidget from '../modals/AddWidget.vue'
import NamedIcon from '../../shared/components/ncIconPicker/NamedIcon.vue'
import { useTablesStore } from '../../store/store.js'
import { GRID_COLUMNS, GRID_MIN_WIDTH, cloneGrid, findWidgetType, gridToWidgets, isStartPage, levelLayout, mergeLayout, nextFreeRow, pageTitle, pageToGrid, tabTitles } from './gridLayout.js'

/**
 * A grid page of an application: its widgets on a grid, with an edit mode that saves the
 * whole layout at once. Cancel drops every change since editing started.
 */
export default {
	name: 'GridPage',
	components: {
		NcActionButton,
		NcActions,
		NcButton,
		NcEmptyContent,
		Check,
		PencilOutline,
		Plus,
		TextBoxEditOutline,
		TrashCanOutline,
		ViewDashboardOutline,
		GridCanvas,
		GridWidget,
		AddWidget,
		NamedIcon,
	},
	provide() {
		return {
			// Widget titles sit below the title of the page, or of the application on a start page without one
			gridHeadingLevel: computed(() => this.showHeading ? 3 : 2),
			// What a tabs widget needs to show and change the widgets in its tabs
			gridActions: {
				grid: () => this.activeGrid,
				nodes: () => this.context.nodes,
				changeLayout: this.onLayoutChange,
				configure: this.configureWidget,
				remove: this.removeWidget,
				move: this.moveWidget,
				addToTab: this.openAddWidgetInTab,
			},
		}
	},
	props: {
		// The application, for its id and its nodes
		context: {
			type: Object,
			required: true,
		},
		// The page as the API returns it, with its widgets
		page: {
			type: Object,
			required: true,
		},
		canEdit: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['saved', 'edit-details', 'delete'],
	setup(props) {
		// The draft lives here, so the route guards and the component share one implementation
		const draftGrid = ref(null)
		const storedGrid = computed(() => pageToGrid(props.page))
		const hasUnsavedChanges = computed(() => draftGrid.value !== null && JSON.stringify(draftGrid.value) !== JSON.stringify(cloneGrid(storedGrid.value)))

		/**
		 * Asks whether unsaved changes may be discarded.
		 *
		 * @return {Promise<boolean>} true when there is nothing to lose or the user agrees
		 */
		const confirmDiscard = async () => {
			if (!hasUnsavedChanges.value) {
				return true
			}
			try {
				return await showConfirmation({
					name: t('tables', 'Discard unsaved changes?'),
					text: t('tables', 'This page has changes that are not saved yet.'),
					labelConfirm: t('tables', 'Discard changes'),
					labelReject: t('tables', 'Keep editing'),
				})
			} catch {
				// Closing the dialog keeps the changes
				return false
			}
		}

		// Leaving the page, or switching to another page, asks first while the grid has unsaved changes
		onBeforeRouteLeave(confirmDiscard)
		onBeforeRouteUpdate(confirmDiscard)

		return { draftGrid, storedGrid, hasUnsavedChanges, confirmDiscard }
	},
	data() {
		return {
			saving: false,
			addWidgetOpen: false,
			widgetBeingConfigured: null,
			// The tab a new widget goes into, as {parentKey, tab}; null for the page itself
			targetTab: null,
		}
	},
	computed: {
		...mapState(useTablesStore, ['widgetTypes']),
		title() {
			return pageTitle(this.page)
		},
		isStartPage() {
			return isStartPage(this.page)
		},
		// The start page sits under the title of its application, so it only has a heading of its own once it gets a title
		showHeading() {
			return !this.isStartPage || !!this.page.title || !!this.page.description
		},
		activeGrid() {
			return this.draftGrid ?? this.storedGrid
		},
		isEditing() {
			return this.draftGrid !== null
		},
		emptyDescription() {
			if (this.isEditing) {
				return t('tables', 'Add a header, a description or a table.')
			}
			return this.canEdit ? t('tables', 'Edit the page to add widgets.') : ''
		},
		// The widgets on the page itself; those in tabs are shown by their tabs widget
		pageLayout() {
			return levelLayout(this.activeGrid)
		},
		widgetsById() {
			return Object.fromEntries(this.activeGrid.widgets.map(widget => [widget.key, widget]))
		},
	},
	watch: {
		'page.id'() {
			this.draftGrid = null
		},
	},
	mounted() {
		window.addEventListener('beforeunload', this.warnBeforeUnload)
	},
	beforeUnmount() {
		window.removeEventListener('beforeunload', this.warnBeforeUnload)
	},
	methods: {
		...mapActions(useTablesStore, ['updateContextPage', 'loadWidgetTypes']),
		/**
		 * Lets the browser warn before the tab closes or reloads with unsaved changes.
		 *
		 * @param {BeforeUnloadEvent} event the unload event
		 */
		warnBeforeUnload(event) {
			if (this.hasUnsavedChanges) {
				event.preventDefault()
			}
		},
		/**
		 * Starts editing on a copy of the stored grid.
		 */
		startEditing() {
			this.draftGrid = cloneGrid(this.storedGrid)
		},
		/**
		 * Leaves edit mode and drops the draft.
		 */
		cancelEditing() {
			this.draftGrid = null
		},
		/**
		 * Takes over the layout after a drag or resize.
		 *
		 * @param {Array} layout the new layout
		 */
		onLayoutChange(layout) {
			if (!this.isEditing) {
				return
			}
			// A canvas shows one level of the grid, the page itself or one tab
			this.draftGrid = { ...this.draftGrid, layout: mergeLayout(this.draftGrid.layout, layout) }
		},
		/**
		 * Opens the dialog for a new widget.
		 */
		openAddWidget() {
			this.widgetBeingConfigured = null
			this.targetTab = null
			this.addWidgetOpen = true
		},
		/**
		 * Opens the dialog for a new widget in a tab of a tabs widget.
		 *
		 * @param {object} tabsWidget the tabs widget
		 * @param {number} tab the position of the tab
		 */
		openAddWidgetInTab(tabsWidget, tab) {
			this.widgetBeingConfigured = null
			this.targetTab = { parentKey: tabsWidget.key, tab }
			this.addWidgetOpen = true
		},
		/**
		 * Closes the widget dialog.
		 */
		closeAddWidget() {
			this.addWidgetOpen = false
			this.widgetBeingConfigured = null
			this.targetTab = null
		},
		/**
		 * Opens the dialog for a widget on the grid.
		 *
		 * @param {object} widget the widget to configure
		 */
		configureWidget(widget) {
			this.widgetBeingConfigured = widget
			this.targetTab = widget.parentKey ? { parentKey: widget.parentKey, tab: widget.parentTab } : null
			this.addWidgetOpen = true
		},
		/**
		 * Updates the configured widget, or places a new one below the others on its level. A tabs
		 * widget that loses tabs moves the widgets of those tabs into its last one.
		 *
		 * @param {{type: string, title: string, icon: string|null, description: string, settings: object, nodeRelId: number|null}} payload the widget from the dialog
		 */
		onWidgetSubmit(payload) {
			if (this.widgetBeingConfigured) {
				const configuredKey = this.widgetBeingConfigured.key
				const lastTab = Math.max(0, tabTitles(payload.settings).length - 1)
				this.draftGrid = {
					...this.draftGrid,
					widgets: this.draftGrid.widgets.map(widget => {
						if (widget.key === configuredKey) {
							return { ...widget, title: payload.title, icon: payload.icon, description: payload.description, settings: payload.settings, nodeRelId: payload.nodeRelId }
						}
						if (widget.parentKey === configuredKey && widget.parentTab > lastTab) {
							return { ...widget, parentTab: lastTab }
						}
						return widget
					}),
				}
				this.closeAddWidget()
				return
			}
			const key = 'new-' + payload.type + '-' + Date.now()
			const widgetType = findWidgetType(this.widgetTypes, payload.type)
			const size = { gridWidth: widgetType?.defaultWidth ?? 6, gridHeight: widgetType?.defaultHeight ?? 3 }
			const parent = this.targetTab ? { parentKey: this.targetTab.parentKey, parentTab: this.targetTab.tab } : { parentKey: null, parentTab: null }
			this.draftGrid = {
				widgets: [...this.draftGrid.widgets, { key, id: null, ...payload, ...parent }],
				layout: [...this.draftGrid.layout, {
					widgetId: key,
					gridX: 0,
					gridY: nextFreeRow(levelLayout(this.draftGrid, parent.parentKey, parent.parentTab)),
					gridWidth: size.gridWidth,
					gridHeight: size.gridHeight,
				}],
			}
			this.closeAddWidget()
		},
		/**
		 * Moves or resizes a widget by one step, kept inside the grid.
		 *
		 * @param {object} widget the widget to change
		 * @param {{gridX?: number, gridY?: number, gridWidth?: number, gridHeight?: number}} change the steps to apply
		 */
		moveWidget(widget, change) {
			this.draftGrid = {
				...this.draftGrid,
				layout: this.draftGrid.layout.map(item => {
					if (item.widgetId !== widget.key) {
						return item
					}
					const gridWidth = Math.min(GRID_COLUMNS, Math.max(GRID_MIN_WIDTH, item.gridWidth + (change.gridWidth ?? 0)))
					return {
						...item,
						gridWidth,
						gridHeight: Math.max(1, item.gridHeight + (change.gridHeight ?? 0)),
						gridX: Math.min(GRID_COLUMNS - gridWidth, Math.max(0, item.gridX + (change.gridX ?? 0))),
						gridY: Math.max(0, item.gridY + (change.gridY ?? 0)),
					}
				}),
			}
		},
		/**
		 * Removes a widget and its place on the grid; a tabs widget takes the widgets in its tabs along.
		 *
		 * @param {object} widget the widget to remove
		 */
		removeWidget(widget) {
			const removed = new Set(this.draftGrid.widgets
				.filter(item => item.key === widget.key || item.parentKey === widget.key)
				.map(item => item.key))
			this.draftGrid = {
				widgets: this.draftGrid.widgets.filter(item => !removed.has(item.key)),
				layout: this.draftGrid.layout.filter(item => !removed.has(item.widgetId)),
			}
		},
		/**
		 * Saves the whole grid at once.
		 */
		async save() {
			if (!this.isEditing) {
				return
			}
			this.saving = true
			// Which types carry a source comes from the server
			await this.loadWidgetTypes()
			const page = await this.updateContextPage({
				contextId: this.context.id,
				pageId: this.page.id,
				// the version this edit started from, so a save by someone else in between is not undone
				data: { widgets: gridToWidgets(this.draftGrid, this.widgetTypes), version: this.page.version },
			})
			this.saving = false
			// A failed save is already reported by the store
			if (page) {
				this.draftGrid = null
				this.$emit('saved', page)
				showSuccess(t('tables', 'Page saved'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.grid-page {
	width: 100%;
	// With the grid margin the widgets line up with the page tabs, the texts in the header
	// with the texts in the widgets
	padding: calc(4 * var(--default-grid-baseline)) calc(6 * var(--default-grid-baseline));

	&__header {
		display: flex;
		flex-wrap: wrap;
		align-items: flex-start;
		justify-content: space-between;
		gap: calc(4 * var(--default-grid-baseline));
		margin-bottom: calc(4 * var(--default-grid-baseline));
		padding-inline: calc(5 * var(--default-grid-baseline)) var(--default-grid-baseline);
	}

	&__title {
		display: flex;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline));
		margin: 0;
	}

	&__description {
		margin: var(--default-grid-baseline) 0 0;
		max-width: 790px;
		color: var(--color-text-maxcontrast);
		white-space: pre-wrap;
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline));
		flex-shrink: 0;
		margin-inline-start: auto;
	}

	&__empty {
		margin-block: 4vh;
	}
}
</style>
