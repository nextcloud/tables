<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-multi-selection" :style="{ opacity: !canEditCell() ? 0.6 : 1 }">
		<div v-if="!isEditing" class="non-edit-mode" @click="handleStartEditing">
			<ul>
				<li v-for="v in getObjects()" :key="v.id">
					{{ v.label }}<span v-if="v.deleted" :title="t('tables', 'This option is outdated.')">&nbsp;⚠️</span>
				</li>
			</ul>
		</div>
		<div v-else
			ref="editingContainer"
			class="edit-mode"
			tabindex="0"
			@keydown.enter.stop="saveChanges"
			@keydown.escape.stop="cancelEdit">
			<NcSelect v-model="editValues"
				:tag-width="80"
				:options="getAllNonDeletedOrSelectedOptions"
				:multiple="true"
				:aria-label-combobox="t('tables', 'Options')"
				:disabled="localLoading || !canEditCell()"
				:clearable="true"
				style="width: 100%;" />
			<div v-if="localLoading" class="loading-indicator">
				<div class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import cellEditMixin from '../mixins/cellEditMixin.js'

export default {
	name: 'TableCellMultiSelection',

	components: {
		NcSelect,
	},

	mixins: [cellEditMixin],

	props: {
		column: {
			type: Object,
			default: () => {},
		},

		rowId: {
			type: Number,
			default: null,
		},

		value: {
			type: Array,
			default: null,
		},
	},

	data() {
		return {
			localEditValues: [],
			isInitialEditClick: false,
		}
	},

	computed: {
		getOptions() {
			return this.column.selectionOptions || []
		},
		getAllNonDeletedOrSelectedOptions() {
			const options = this.getOptions.filter(item => {
				return !item.deleted || this.optionIdIsSelected(item.id)
			}) || []

			options.forEach(opt => {
				if (opt.deleted) {
					opt.label += ' ⚠️'
				}
			})
			return options
		},
		editValues: {
			get() {
				return this.localEditValues
			},
			set(newValues) {
				this.localEditValues = newValues || []
			},
		},
	},

	watch: {
		isEditing(isEditing) {
			if (isEditing) {
				this.initEditValues()
				// Add click outside listener after the current event loop
				// to avoid the same click that triggered editing from closing the editor
				this.$nextTick(() => {
					document.addEventListener('click', this.handleClickOutside)
				})
			} else {
				document.removeEventListener('click', this.handleClickOutside)
				this.isInitialEditClick = false
			}
		},
	},

	methods: {
		t,

		handleStartEditing(event) {
			this.isInitialEditClick = true
			this.startEditing()
			// Stop the event from propagating to avoid immediate click outside
			event.stopPropagation()
		},

		getObjects() {
			return this.column.getObjects(this.value)
		},

		optionIdIsSelected(id) {
			// Check if the given id is selected (in the value array)
			return this.value && this.value.includes(id)
		},

		getIdArrayFromObjects(objects) {
			const ids = []
			objects.forEach(o => {
				ids.push(o.id)
			})
			return ids
		},

		initEditValues() {
			if (this.value !== null) {
				this.localEditValues = this.column.getObjects(this.value)
			} else {
				this.localEditValues = []
			}
		},
		cancelEdit() {
			this.isEditing = false
			this.localEditValues = []
		},

		async saveChanges() {
			if (this.localLoading) {
				return
			}

			const newValue = this.getIdArrayFromObjects(this.editValues)

			const success = await this.updateCellValue(newValue)

			if (success) {
				// trigger immediate re-render
				this.$emit('input', newValue)
				this.$emit('update:value', newValue)
				this.isEditing = false
			} else {
				this.cancelEdit()
			}

			this.localLoading = false
		},

		handleClickOutside(event) {
			// Ignore the initial click that started editing
			if (this.isInitialEditClick) {
				this.isInitialEditClick = false
				return
			}

			// Check if the click is outside the editing container
			// But ignore clicks on dropdown options and scrollbars
			if (this.$refs.editingContainer && !this.$refs.editingContainer.contains(event.target)) {
				this.saveChanges()
			}
		},
	},
}
</script>
<style lang="scss" scoped>
.cell-multi-selection {
	width: 100%;

	.non-edit-mode {
		cursor: pointer;
		min-height: 20px;
	}
}

.edit-mode {
	.editor-buttons {
		display: flex;
		gap: 8px;
		margin-top: 8px;
		align-items: center;
	}

	.icon-loading-inline {
		margin-inline-start: 4px;
	}
}

ul {
	list-style-type: disc;
	padding-inline-start: calc(var(--default-grid-baseline) * 3);
}
</style>
