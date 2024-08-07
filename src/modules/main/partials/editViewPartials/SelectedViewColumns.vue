<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="selected-columns-wrapper"
		@dragenter.prevent
		@dragover.prevent>
		<div v-for="(column, index) in columns" :key="column.id" :draggable="true" :class="{'locallyRemoved': isLocallyRemoved(column.id)}"
			class="column-entry" @dragstart="dragStart(index)"
			@dragover="dragOver(index)" @dragend="dragEnd(index)">
			<div class="row-elements">
				<NcButton
					aria-label="t('tables', 'Move')"
					type="tertiary-no-background"
					class="move-button">
					<template #icon>
						<DragHorizontalVariant :size="20" />
					</template>
				</NcButton>
				<NcCheckboxRadioSwitch
					v-if="!disableHide"
					:checked="selectedColumns.includes(column.id)"
					class="display-checkbox"
					@update:checked="onToggle(column.id)" />
				{{ column.title }}
				<div v-if="column.id < 0" class="meta-info">
					({{ t('tables', 'Metadata') }})
				</div>
			</div>
			<div class="row-elements move">
				<NcButton
					:disabled="index === 0"
					aria-label="Move"
					type="tertiary-no-background"
					@click="moveColumn(index, -1)">
					<template #icon>
						<ArrowUp :size="20" />
					</template>
				</NcButton>
				<NcButton
					:disabled="index >= columns.length - 1"
					aria-label="Move"
					type="tertiary-no-background"
					@click="moveColumn(index, 1)">
					<template #icon>
						<ArrowDown :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import DragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'

export default {
	name: 'SelectedViewColumns',
	components: {
		NcButton,
		DragHorizontalVariant,
		NcCheckboxRadioSwitch,
		ArrowUp,
		ArrowDown,
	},
	props: {
		columns: {
			type: Array,
			default: null,
		},
		selectedColumns: {
			type: Array,
			default: null,
		},
		viewColumnIds: {
			type: Array,
			default: null,
		},
		generatedColumnIds: {
			type: Array,
			default: null,
		},
		disableHide: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			mutableColumns: this.columns,
			mutableSelectedColumns: this.selectedColumns,
			draggedItem: null,
			startDragIndex: null,
		}
	},
	methods: {
		isLocallyRemoved(columnId) {
			if (!this.viewColumnIds || !this.generatedColumnIds) return false
			return !this.selectedColumns.includes(columnId) && !this.generatedColumnIds.includes(columnId) && this.viewColumnIds.includes(columnId)
		},
		onToggle(columnId) {
			if (this.mutableSelectedColumns.includes(columnId)) {
				this.mutableSelectedColumns.splice(this.mutableSelectedColumns.indexOf(columnId), 1)
			} else {
				this.mutableSelectedColumns.push(columnId)
			}
		},
		reset() {
			this.mutableColumns = null
		},
		dragStart(index) {
			this.draggedItem = this.mutableColumns[index]
			this.startDragIndex = index
		},
		dragOver(index) {
			if (this.draggedItem === null) return
			const draggedIndex = this.columns.indexOf(this.draggedItem)
			if (index !== draggedIndex) {
				this.mutableColumns.splice(draggedIndex, 1)
				this.mutableColumns.splice(index, 0, this.draggedItem)
			}
		},
		moveColumn(index, direction) {
			const item = this.mutableColumns[index]
			this.mutableColumns.splice(index, 1)
			this.mutableColumns.splice(index + direction, 0, item)
		},
		async dragEnd(goalIndex) {
			if (this.draggedItem === null) return
			const goal = goalIndex !== undefined ? goalIndex : this.list.indexOf(this.draggedItem)
			if (this.startDragIndex === goal) return
			this.draggedItem = null
			this.startDragIndex = null
		},
	},
}
</script>

<style lang="scss" scoped>

.column-entry {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: calc(var(--default-grid-baseline) * 1) 0;
	border-radius: var(--border-radius-large);
}

.column-entry:hover {
	background-color: var(--color-background-dark);
}

.display-checkbox {
	padding-right: calc(var(--default-grid-baseline) * 4);
}

:deep(.modal-container) {
	min-width: 60% !important;
}

:deep(.button-vue) {
	cursor: move !important;
	min-height: auto !important;
	min-width: auto !important;
}

:deep(.button-vue__icon) {
	height: auto !important;
	width: auto !important;
	min-height: auto !important;
	min-width: auto !important;
}

:deep(.checkbox-radio-switch__label) {
	min-height: auto !important;
	padding: 4px !important;
}

:deep(.checkbox-radio-switch__icon) {
	margin-right: 0 !important;
	margin-left: 0 !important;
}

.selected-columns-wrapper {
	display: flex;
	flex-direction: column;
}

.move-button {
	padding-right: 10px !important;
	cursor: move !important;
}

.move-button:hover {
	cursor: move !important;
}

.meta-info {
	font-style: italic;
	padding-left:  calc(var(--default-grid-baseline) * 1);
	color: var(--color-info);
}

.row-elements {
	display: flex;
	align-items: center;
}

.row-elements.move {
	display: none;
}

.column-entry:hover .row-elements.move, .column-entry:focus-within .row-elements.move {
	display: flex;
}

.locallyRemoved {
	background-color: var(--color-error-hover);
}
</style>
