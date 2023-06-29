<template>
	<div class="selected-columns-wrapper">
		<div class="col-4 mandatory">
			{{ t('tables', 'Columns to be displayed') }}
		</div>
		<div v-for="(column, index) in columns" :key="column.id" :draggable="true"
			style="display: flex; align-items: center;" @dragstart="dragStart(index)"
			@dragover="dragOver(index)" @dragend="dragEnd(index)">
			<NcButton aria-label="Move" type="tertiary-no-background"
				style="padding-right: 10px;">
				<template #icon>
					<MenuIcon :size="20" />
				</template>
			</NcButton>
			<NcCheckboxRadioSwitch :checked="selectedColumns.includes(column.id)"
				style="padding-right: 10px;" @update:checked="onToggle(column.id)" />
			{{ column.title }}
		</div>
	</div>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import MenuIcon from 'vue-material-design-icons/Menu.vue'

export default {
	name: 'SelectedViewColumns',
	components: {
		NcButton,
		MenuIcon,
		NcCheckboxRadioSwitch,
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

:deep(.modal-container) {
	min-width: 60% !important;
}
.selected-columns-wrapper {
	display: flex;
	flex-direction: column;
}

</style>
