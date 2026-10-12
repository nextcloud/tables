<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="grid-view" data-cy="gridView">
		<div class="grid-view__header">
			<div class="grid-view__heading">
				<h1 v-if="showTitle" class="grid-view__title" data-cy="gridViewHeading">
					{{ view.emoji }} {{ view.title }}
				</h1>
				<p v-if="showTitle && view.description" class="grid-view__description">
					{{ view.description }}
				</p>
			</div>
			<div v-if="canEdit" class="grid-view__actions">
				<template v-if="isEditing">
					<NcButton variant="secondary" data-cy="gridViewAddWidget" @click="openAddWidget">
						<template #icon>
							<Plus :size="20" />
						</template>
						{{ t('tables', 'Add widget') }}
					</NcButton>
					<NcButton variant="tertiary" data-cy="gridViewCancel" @click="cancelEditing">
						{{ t('tables', 'Cancel') }}
					</NcButton>
					<NcButton variant="primary" :disabled="saving" data-cy="gridViewSave" @click="save">
						<template #icon>
							<ContentSaveOutline :size="20" />
						</template>
						{{ t('tables', 'Save') }}
					</NcButton>
				</template>
				<NcButton v-else variant="secondary" data-cy="gridViewEdit" @click="startEditing">
					<template #icon>
						<PencilOutline :size="20" />
					</template>
					{{ t('tables', 'Edit page') }}
				</NcButton>
			</div>
		</div>

		<NcEmptyContent v-if="activeGrid.layout.length === 0"
			class="grid-view__empty"
			:name="t('tables', 'This page is empty')"
			:description="isEditing ? t('tables', 'Add a header, a description or a table.') : t('tables', 'Edit the page to add widgets.')">
			<template #icon>
				<ViewDashboardOutline />
			</template>
			<template v-if="isEditing" #action>
				<NcButton variant="primary" @click="openAddWidget">
					{{ t('tables', 'Add widget') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<GridCanvas :layout="activeGrid.layout" :editable="isEditing" @layout-change="onLayoutChange">
			<template #widget="{ item, narrow }">
				<GridWidget v-if="widgetsById[item.widgetId]"
					:widget="widgetsById[item.widgetId]"
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
			@close="closeAddWidget"
			@submit="onWidgetSubmit" />
	</div>
</template>

<script>
import { NcButton, NcEmptyContent } from '@nextcloud/vue'
import { showConfirmation, showSuccess } from '@nextcloud/dialogs'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import { computed, ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import { mapActions, mapState } from 'pinia'
import GridCanvas from './GridCanvas.vue'
import GridWidget from './GridWidget.vue'
import AddWidget from '../modals/AddWidget.vue'
import { useTablesStore } from '../../store/store.js'
import { GRID_COLUMNS, GRID_MIN_WIDTH, cloneGrid, emptyGrid, findWidgetType, nextFreeRow } from './gridLayout.js'

/**
 * A grid view: its widgets on a grid, with an edit mode that saves the whole grid at once.
 */
export default {
	name: 'GridView',
	components: {
		NcButton,
		NcEmptyContent,
		ContentSaveOutline,
		PencilOutline,
		Plus,
		ViewDashboardOutline,
		GridCanvas,
		GridWidget,
		AddWidget,
	},
	props: {
		view: {
			type: Object,
			required: true,
		},
		canEdit: {
			type: Boolean,
			default: false,
		},
		showTitle: {
			type: Boolean,
			default: true,
		},
	},
	setup(props) {
		// The draft lives here, so the route guards and the component share one implementation
		const draftGrid = ref(null)
		const storedGrid = computed(() => props.view.grid?.layout ? props.view.grid : emptyGrid())
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

		// Leaving the page, or switching to another view, asks first while the grid has unsaved changes
		onBeforeRouteLeave(confirmDiscard)
		onBeforeRouteUpdate(confirmDiscard)

		return { draftGrid, storedGrid, hasUnsavedChanges, confirmDiscard }
	},
	data() {
		return {
			saving: false,
			addWidgetOpen: false,
			widgetBeingConfigured: null,
		}
	},
	computed: {
		...mapState(useTablesStore, ['widgetTypes']),
		activeGrid() {
			return this.draftGrid ?? this.storedGrid
		},
		isEditing() {
			return this.draftGrid !== null
		},
		widgetsById() {
			return Object.fromEntries(this.activeGrid.widgets.map(widget => [widget.id, widget]))
		},
	},
	watch: {
		'view.id'() {
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
		...mapActions(useTablesStore, ['updateView']),
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
			this.draftGrid = { ...this.draftGrid, layout }
		},
		/**
		 * Opens the dialog for a new widget.
		 */
		openAddWidget() {
			this.widgetBeingConfigured = null
			this.addWidgetOpen = true
		},
		/**
		 * Closes the widget dialog.
		 */
		closeAddWidget() {
			this.addWidgetOpen = false
			this.widgetBeingConfigured = null
		},
		/**
		 * Opens the dialog for a widget on the grid.
		 *
		 * @param {object} widget the widget to configure
		 */
		configureWidget(widget) {
			this.widgetBeingConfigured = widget
			this.addWidgetOpen = true
		},
		/**
		 * Updates the configured widget, or places a new one below the others.
		 *
		 * @param {{type: string, configuration: object, content: object}} payload the widget from the dialog
		 */
		onWidgetSubmit(payload) {
			if (this.widgetBeingConfigured) {
				this.draftGrid = {
					...this.draftGrid,
					widgets: this.draftGrid.widgets.map(widget => widget.id === this.widgetBeingConfigured.id
						? { ...widget, configuration: payload.configuration, content: payload.content }
						: widget),
				}
				this.closeAddWidget()
				return
			}
			const id = 'w-' + payload.type + '-' + Date.now()
			const widgetType = findWidgetType(this.widgetTypes, payload.type)
			const size = { gridWidth: widgetType?.defaultWidth ?? 6, gridHeight: widgetType?.defaultHeight ?? 3 }
			this.draftGrid = {
				widgets: [...this.draftGrid.widgets, { id, type: payload.type, configuration: payload.configuration, content: payload.content }],
				layout: [...this.draftGrid.layout, {
					id,
					widgetId: id,
					gridX: 0,
					gridY: nextFreeRow(this.draftGrid.layout),
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
					if (item.widgetId !== widget.id) {
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
		 * Removes a widget and its place on the grid.
		 *
		 * @param {object} widget the widget to remove
		 */
		removeWidget(widget) {
			this.draftGrid = {
				widgets: this.draftGrid.widgets.filter(item => item.id !== widget.id),
				layout: this.draftGrid.layout.filter(item => item.widgetId !== widget.id),
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
			const grid = cloneGrid(this.draftGrid)
			const success = await this.updateView({
				id: this.view.id,
				data: { data: { grid: JSON.stringify(grid) } },
			})
			this.saving = false
			// A failed save is already reported by the store
			if (success) {
				this.draftGrid = null
				showSuccess(t('tables', 'Page saved'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.grid-view {
	width: 100%;
	padding: calc(4 * var(--default-grid-baseline));

	&__header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: calc(4 * var(--default-grid-baseline));
		margin-bottom: calc(4 * var(--default-grid-baseline));
	}

	&__title {
		margin: 0;
	}

	&__description {
		margin: var(--default-grid-baseline) 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__actions {
		display: flex;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline));
		flex-shrink: 0;
	}

	&__empty {
		margin-block: 4vh;
	}
}
</style>
