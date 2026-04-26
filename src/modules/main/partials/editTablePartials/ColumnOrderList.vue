<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="selected-columns-wrapper"
		@dragenter.prevent
		@dragover.prevent>
		<div v-for="(column, index) in mutableColumns"
			:key="column.id"
			:draggable="true"
			class="column-entry"
			@dragstart="dragStart(index)"
			@dragover="dragOver(index)"
			@dragend="dragEnd(index)">
			<div class="row-elements">
				<NcButton
					:aria-label="t('tables', 'Move')"
					type="tertiary-no-background"
					class="move-button">
					<template #icon>
						<DragHorizontalVariant :size="20" />
					</template>
				</NcButton>
				<span>{{ column.title }}</span>
				<div v-if="column.id < 0" class="meta-info">
					({{ t('tables', 'Metadata') }})
				</div>
			</div>

			<div class="row-elements move">
				<NcButton
					:disabled="index === 0"
					:aria-label="t('tables', 'Move up')"
					type="tertiary-no-background"
					@click="moveColumn(index, -1)">
					<template #icon>
						<ArrowUp :size="20" />
					</template>
				</NcButton>
				<NcButton
					:disabled="index >= mutableColumns.length - 1"
					:aria-label="t('tables', 'Move down')"
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
import { NcButton } from '@nextcloud/vue'
import DragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import dragReorderMixin from '../../../../shared/mixins/dragReorderMixin.js'

export default {
	name: 'ColumnOrderList',

	components: {
		NcButton,
		DragHorizontalVariant,
		ArrowUp,
		ArrowDown,
	},

	mixins: [dragReorderMixin],
	props: {
		columns: {
			type: Array,
			default: null,
		},
	},
	emits: [
		'update:columnSettings',
	],

	data() {
		return {
			mutableColumns: this.columns ? [...this.columns] : [],
		}
	},

	computed: {
		reorderableList() {
			return this.mutableColumns
		},
	},
	watch: {
		columns: {
			handler(newColumns) {
				this.mutableColumns = newColumns ? [...newColumns] : []
			},
			deep: true,
			immediate: true,
		},
	},

	methods: {
		emitColumnSettings() {
			const columnSettings = this.mutableColumns.map((column, index) => ({
				columnId: column.id,
				order: index + 1,
				readonly: false,
				mandatory: false,
			}))
			this.$emit('update:columnSettings', columnSettings)
		},
		onReorderCommitted() {
			this.emitColumnSettings()
		},
		moveColumn(index, direction) {
			const item = this.mutableColumns[index]
			this.mutableColumns.splice(index, 1)
			this.mutableColumns.splice(index + direction, 0, item)
			this.emitColumnSettings()
		},
	},
}
</script>

<style lang="scss" scoped>

.selected-columns-wrapper {
	display: flex;
	flex-direction: column;
}

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

.row-elements {
	display: flex;
	align-items: center;
}

.row-elements.move {
	display: none;
}

.column-entry:hover .row-elements.move,
.column-entry:focus-within .row-elements.move {
	display: flex;
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
	padding-inline-start: calc(var(--default-grid-baseline) * 1);
	color: var(--color-info);
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

</style>
