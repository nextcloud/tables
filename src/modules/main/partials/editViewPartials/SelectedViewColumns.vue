<template>
	<div class="selected-columns-wrapper"
		@dragenter.prevent
		@dragover.prevent>
		<div v-for="(column, index) in columns" :key="column.id" :draggable="true"
			class="column-entry" @dragstart="dragStart(index)"
			@dragover="dragOver(index)" @dragend="dragEnd(index)">
			<div class="row-elements">
				<NcButton
					aria-label="Move"
					type="tertiary-no-background"
					class="move-button">
					<template #icon>
						<DragHorizontalVariant :size="20" />
					</template>
				</NcButton>
				<NcCheckboxRadioSwitch
					:disabled="isBaseView && column.id >= 0"
					:checked="selectedColumns.includes(column.id)"
					class="display-checkbox"
					@update:checked="onToggle(column.id)" />
				{{ column.title }}
				<div v-if="column.id < 0" class="meta-info">
					({{ t('tables', 'Metadata') }})
				</div>
			</div>
			<div class="row-elements">
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
		isBaseView: {
			type: Boolean,
			default: false,
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
	min-height: auto;
	padding: 4px;
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

.meta-info {
	font-style: italic;
	padding-left:  calc(var(--default-grid-baseline) * 1);
	color: var(--color-info);
}

.row-elements {
	display: flex;
}

</style>
