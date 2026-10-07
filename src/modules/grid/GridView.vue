<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="grid-view" data-cy="grid-view">
		<div class="grid-view__header">
			<div class="grid-view__heading">
				<h1 v-if="showTitle" class="grid-view__title" data-cy="grid-view-title">
					{{ view.emoji }} {{ view.title }}
				</h1>
				<p v-if="showTitle && view.description" class="grid-view__description">
					{{ view.description }}
				</p>
			</div>
			<div v-if="canEdit" class="grid-view__actions">
				<template v-if="isEditing">
					<NcButton variant="secondary" data-cy="grid-view-add-widget" @click="openAddWidget">
						<template #icon>
							<Plus :size="20" />
						</template>
						{{ t('tables', 'Add widget') }}
					</NcButton>
					<NcButton variant="tertiary" data-cy="grid-view-cancel" @click="cancelEditing">
						{{ t('tables', 'Cancel') }}
					</NcButton>
					<NcButton variant="primary" :disabled="saving" data-cy="grid-view-save" @click="save">
						<template #icon>
							<ContentSaveOutline :size="20" />
						</template>
						{{ t('tables', 'Save') }}
					</NcButton>
				</template>
				<NcButton v-else variant="secondary" data-cy="grid-view-edit" @click="startEditing">
					<template #icon>
						<PencilOutline :size="20" />
					</template>
					{{ t('tables', 'Edit page') }}
				</NcButton>
			</div>
		</div>

		<NcEmptyContent v-if="activeGrid.layout.length === 0"
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
			<template #widget="{ item }">
				<GridWidget v-if="widgetsById[item.widgetId]"
					:widget="widgetsById[item.widgetId]"
					:editable="isEditing"
					@configure="configureWidget"
					@remove="removeWidget" />
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import ContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import { mapActions } from 'pinia'
import GridCanvas from './GridCanvas.vue'
import GridWidget from './GridWidget.vue'
import AddWidget from '../modals/AddWidget.vue'
import { useTablesStore } from '../../store/store.js'
import { cloneGrid, emptyGrid, getWidgetType, nextFreeRow } from './widgetRegistry.js'

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
	data() {
		return {
			draftGrid: null,
			saving: false,
			addWidgetOpen: false,
			widgetBeingConfigured: null,
		}
	},
	computed: {
		storedGrid() {
			return this.view.grid?.layout ? this.view.grid : emptyGrid()
		},
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
	methods: {
		...mapActions(useTablesStore, ['updateView']),
		startEditing() {
			this.draftGrid = cloneGrid(this.storedGrid)
		},
		cancelEditing() {
			this.draftGrid = null
		},
		onLayoutChange(layout) {
			if (!this.isEditing) {
				return
			}
			this.draftGrid = { ...this.draftGrid, layout }
		},
		openAddWidget() {
			this.widgetBeingConfigured = null
			this.addWidgetOpen = true
		},
		closeAddWidget() {
			this.addWidgetOpen = false
			this.widgetBeingConfigured = null
		},
		configureWidget(widget) {
			this.widgetBeingConfigured = widget
			this.addWidgetOpen = true
		},
		onWidgetSubmit(payload) {
			if (this.widgetBeingConfigured) {
				this.draftGrid = {
					...this.draftGrid,
					widgets: this.draftGrid.widgets.map(widget => widget.id === this.widgetBeingConfigured.id
						? { ...widget, title: payload.title, showTitle: payload.showTitle, content: payload.content }
						: widget),
				}
				this.closeAddWidget()
				return
			}
			const id = 'w-' + payload.type + '-' + Date.now()
			const size = getWidgetType(payload.type)?.defaultSize ?? { gridWidth: 6, gridHeight: 3 }
			this.draftGrid = {
				widgets: [...this.draftGrid.widgets, { id, type: payload.type, title: payload.title, showTitle: payload.showTitle, content: payload.content }],
				layout: [...this.draftGrid.layout, {
					id: this.draftGrid.layout.length + 1,
					widgetId: id,
					gridX: 0,
					gridY: nextFreeRow(this.draftGrid.layout),
					gridWidth: size.gridWidth,
					gridHeight: size.gridHeight,
				}],
			}
			this.closeAddWidget()
		},
		removeWidget(widget) {
			this.draftGrid = {
				widgets: this.draftGrid.widgets.filter(item => item.id !== widget.id),
				layout: this.draftGrid.layout.filter(item => item.widgetId !== widget.id),
			}
		},
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
			if (success) {
				this.draftGrid = null
				showSuccess(t('tables', 'Page saved'))
			} else {
				showError(t('tables', 'The page could not be saved'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.grid-view {
	width: 100%;
	padding: calc(4 * var(--default-grid-baseline, 4px));

	&__header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: calc(4 * var(--default-grid-baseline, 4px));
		margin-bottom: calc(4 * var(--default-grid-baseline, 4px));
	}

	&__title {
		margin: 0;
		font-size: 24px;
		font-weight: bold;
	}

	&__description {
		margin: calc(1 * var(--default-grid-baseline, 4px)) 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__actions {
		display: flex;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline, 4px));
		flex-shrink: 0;
	}

	:deep(.empty-content) {
		margin-top: 4vh;
		margin-bottom: 4vh;
	}
}
</style>
