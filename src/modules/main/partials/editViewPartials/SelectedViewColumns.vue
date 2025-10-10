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
			<div data-cy="selectedViewColumnEl" class="row-elements">
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
				<span :class="{ 'title-readonly': column.viewColumnInformation?.readonly }">
					{{ column.title }}
					<span v-if="isMandatory(column)" class="mandatory-indicator">*</span>
				</span>
				<div v-if="column.id < 0" class="meta-info">
					({{ t('tables', 'Metadata') }})
				</div>
			</div>

			<div class="row-elements actions">
				<NcActions v-if="column.id > 0" data-cy="customColumnAction">
					<!-- Read only -->
					<NcActionCheckbox
						v-if="selectedColumns.includes(column.id)"
						data-cy="columnReadonlyCheckbox"
						:checked="column.viewColumnInformation?.readonly"
						:disabled="isMandatory(column)"
						@change="onReadonlyChanged(column.id, $event.target.checked)">
						{{ t('tables', 'Read only') }}
					</NcActionCheckbox>
					<!-- Mandatory -->
					<NcActionCheckbox
						v-if="selectedColumns.includes(column.id)"
						data-cy="columnMandatoryCheckbox"
						:checked="isMandatory(column)"
						:disabled="column.viewColumnInformation?.readonly"
						@change="onMandatoryChanged(column.id, $event.target.checked)">
						{{ t('tables', 'Mandatory') }}
					</NcActionCheckbox>
				</NcActions>
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
import { NcActionCheckbox, NcActions, NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import DragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import rowHelper from '../../../../shared/components/ncTable/mixins/rowHelper.js'

export default {
	name: 'SelectedViewColumns',
	components: {
		NcActionCheckbox,
		NcActions,
		NcButton,
		DragHorizontalVariant,
		NcCheckboxRadioSwitch,
		ArrowUp,
		ArrowDown,
	},
	mixins: [rowHelper],
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
	watch: {
		columns: {
			handler(newColumns) {
				this.mutableColumns = newColumns
			},
			deep: true,
			immediate: true,
		},
		selectedColumns: {
			handler(newSelectedColumns) {
				this.mutableSelectedColumns = newSelectedColumns
			},
			deep: true,
			immediate: true,
		},
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
		onReadonlyChanged(columnId, readonly) {
			const column = this.mutableColumns.find(col => col.id === columnId)
			if (!column) return

			if (!column.viewColumnInformation) {
				this.$set(column, 'viewColumnInformation', {})
			}

			this.$set(column.viewColumnInformation, 'readonly', readonly)
		},
		onMandatoryChanged(columnId, mandatory) {
			const column = this.mutableColumns.find(col => col.id === columnId)
			if (!column) return

			if (!column.viewColumnInformation) {
				this.$set(column, 'viewColumnInformation', {})
			}

			this.$set(column.viewColumnInformation, 'mandatory', mandatory)
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
	padding-inline-end: calc(var(--default-grid-baseline) * 4);
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
	margin-inline: 0 !important;
}

.selected-columns-wrapper {
	display: flex;
	flex-direction: column;
}

.move-button {
	padding-inline-end: 10px !important;
	cursor: move !important;
}

.move-button:hover {
	cursor: move !important;
}

.meta-info {
	font-style: italic;
	padding-inline-start:  calc(var(--default-grid-baseline) * 1);
	color: var(--color-info);
}

.row-elements {
	display: flex;
	align-items: center;
}

.row-elements .title-readonly {
  opacity: 0.6;
}

.row-elements.move, .row-elements.actions {
	display: none;
}

.row-elements.actions {
	margin-left: auto;
}

.column-entry:hover .row-elements.move, .column-entry:focus-within .row-elements.move, .column-entry:hover .row-elements.actions, .column-entry:focus-within .row-elements.actions {
	display: flex;
}

.locallyRemoved {
	background-color: var(--color-error-hover);
}

.mandatory-indicator {
  color: var(--color-error);
  margin-left: 4px;
  font-size: 16px;
  line-height: 1;
}
</style>
