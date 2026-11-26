<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-selection">
		<div v-if="!isEditing" class="non-edit-mode" @click="handleStartEditing">
			<template v-if="selectedOption.deleted">
				<span class="outdated-option-label">{{ selectedOptionId }}</span><span
					class="outdated-option-indicator"
					:title="t('tables', 'This option is outdated.')">⚠️</span>
			</template>
			<span v-else>{{ selectedOption?.label }}</span>
		</div>
		<div v-else
			ref="editingContainer"
			class="edit-mode"
			tabindex="0"
			@keydown.enter.stop="saveChanges"
			@keydown.escape.stop="cancelEdit">
			<NcSelect v-model="editValue"
				:options="selectableOptions"
				:selectable="option => !option?.deleted"
				:clearable="!column.mandatory"
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
import { NcSelect } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import cellEditMixin from '../mixins/cellEditMixin.js'

export default {
	name: 'TableCellSelection',

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
			type: Number,
			default: null,
		},
	},

	data() {
		return {
			isInitialEditClick: false,
		}
	},

	computed: {
		options() {
			return this.column?.selectionOptions || []
		},
		selectedOptionId() {
			const optionId = parseInt(this.value)
			return Number.isNaN(optionId) ? null : optionId
		},
		selectedOption() {
			if (this.selectedOptionId === null) {
				return null
			}
			return this.column.getOptionObject(this.selectedOptionId)
		},
		selectableOptions() {
			if (this.selectedOption?.deleted) {
				return [...this.options, this.selectedOption]
			}
			return this.options
		},
	},

	watch: {
		isEditing(isEditing) {
			if (isEditing) {
				this.initEditValue()
				// Add click outside listener after the current event loop
				// to avoid the same click that triggered editing from closing the editor
				this.$nextTick(() => {
					document.addEventListener('click', this.handleClickOutside)
				})
			} else {
				// Remove click outside listener
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

		initEditValue() {
			if (this.value !== null && this.value !== undefined) {
				this.editValue = this.selectedOption
			} else {
				this.editValue = null
			}
		},
		async saveChanges() {
			if (this.localLoading) {
				return
			}

			const newValue = this.editValue?.id

			const success = await this.updateCellValue(newValue)

			if (!success) {
				this.cancelEdit()
			}

			this.localLoading = false
			this.isEditing = false
		},

		handleClickOutside(event) {
			// Ignore the initial click that started editing
			if (this.isInitialEditClick) {
				this.isInitialEditClick = false
				return
			}

			// Check if the click is outside the editing container
			if (this.$refs.editingContainer && !this.$refs.editingContainer.contains(event.target)) {
				this.saveChanges()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.cell-selection {
	width: 100%;

	.non-edit-mode {
		cursor: pointer;
		min-height: 20px;
	}
}

:deep(.vs__dropdown-toggle) {
    border: var(--vs-border-width) var(--vs-border-style) var(--vs-border-color);
    border-radius: var(--vs-border-radius);
}

.edit-mode {
	.icon-loading-inline {
		margin-inline-start: 4px;
	}
}

.outdated-option-indicator {
	cursor: help;
}

.outdated-option-label {
	opacity: 0.6;
}
</style>
