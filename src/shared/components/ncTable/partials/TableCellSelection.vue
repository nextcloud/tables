<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-selection">
		<div v-if="!isEditing" class="non-edit-mode" @click="startEditing">
			{{ column.getLabel(value) }}<span v-if="isDeleted()" :title="t('tables', 'This option is outdated.')">&nbsp;⚠️</span>
		</div>
		<div v-else
			ref="editingContainer"
			class="edit-mode"
			tabindex="0"
			@keydown.enter.stop="saveChanges"
			@keydown.escape.stop="cancelEdit">
			<NcSelect v-model="editValue"
				:options="getAllNonDeletedOptions"
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

	computed: {
		getOptions() {
			return this.column?.selectionOptions || []
		},
		getAllNonDeletedOptions() {
			return this.getOptions.filter(item => {
				return !item.deleted
			})
		},
	},

	watch: {
		isEditing(isEditing) {
			if (isEditing) {
				this.initEditValue()
				// Use a small delay to prevent the same click event that triggered editing
				// from immediately triggering the click outside handler
				// TODO: implement better click outside detection without setTimeout
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

		isDeleted() {
			return this.column.isDeletedLabel(this.value)
		},

		getOptionObject(id) {
			return this.getOptions.find(e => e.id === id) || null
		},

		initEditValue() {
			if (this.value !== null) {
				this.editValue = this.getOptionObject(parseInt(this.value))
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

.edit-mode {
	.icon-loading-inline {
		margin-left: 4px;
	}
}

span {
	cursor: help;
}
</style>
