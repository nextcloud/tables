<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-multi-selection">
		<div v-if="!isEditing" class="non-edit-mode" @click="startEditing">
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
			@keydown.enter="saveChanges"
			@keydown.escape="cancelEdit">
			<NcSelect v-model="editValues"
				:tag-width="80"
				:options="getAllNonDeletedOrSelectedOptions"
				:multiple="true"
				:aria-label-combobox="t('tables', 'Options')"
				:disabled="localLoading || !canEditCell()"
				style="width: 100%;" />
			<div v-if="localLoading" class="loading-indicator">
				<div class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcSelect, NcButton } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import cellEditMixin from '../mixins/cellEditMixin.js'

export default {
	name: 'TableCellMultiSelection',

	components: {
		NcSelect,
		NcButton,
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
	},

	watch: {
		isEditing(isEditing) {
			if (isEditing) {
				this.initEditValues()
				// Use a small delay to prevent the same click event that triggered editing
				// from immediately triggering the click outside handler
				this.$nextTick(() => {
					setTimeout(() => {
						document.addEventListener('click', this.handleClickOutside)
					}, 10)
				})
			} else {
				// Remove click outside listener
				document.removeEventListener('click', this.handleClickOutside)
			}
		},
	},

	methods: {
		t,

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
				this.editValues = this.column.getObjects(this.value)
			} else {
				this.editValues = []
			}
		},
		async saveChanges() {
			if (this.localLoading) {
				return
			}

			const newValue = this.getIdArrayFromObjects(this.editValues)

			const success = await this.updateCellValue(newValue)

			if (success) {
				// Emit the updated value to parent to trigger immediate re-render
				this.$emit('input', newValue)
				this.$emit('update:value', newValue)
				this.isEditing = false
			} else {
				this.cancelEdit()
			}

			this.localLoading = false
		},

		handleClickOutside(event) {
			// Check if the click is outside the editing container
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
		margin-left: 4px;
	}
}

ul {
	list-style-type: disc;
	padding-left: calc(var(--default-grid-baseline) * 3);
}
</style>
