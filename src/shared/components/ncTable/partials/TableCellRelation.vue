<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-relation">
		<div v-if="!isEditing" class="non-edit-mode" @click="handleStartEditing">
			<div v-if="isDeleted">
				<span class="deleted">{{ value }}</span>
				<span class="cursor-help" :title="t('tables', 'This relation is not exists anymore.')">&nbsp;⚠️</span>
			</div>
			<div v-else>
				{{ relationLabel }}
			</div>
		</div>
		<div v-else
			ref="editingContainer"
			class="edit-mode"
			tabindex="0"
			@keydown.enter.stop="saveChanges"
			@keydown.escape.stop="cancelEdit">
			<NcSelect v-model="editValue"
				:options="relationOptions"
				:clearable="!column.mandatory"
				:reduce="(option) => option.id"
				:aria-label-combobox="t('tables', 'Select relation value')"
				:disabled="localLoading || !canEditCell()"
				style="width: 100%;" />
			<div v-if="localLoading" class="loading-indicator">
				<div class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcSelect } from '@nextcloud/vue'
import cellEditMixin from '../mixins/cellEditMixin.js'
import { useDataStore } from '../../../../store/data.js'
import { mapState } from 'pinia'
import { useTablesStore } from '../../../../store/store.js'

export default {
	name: 'TableCellRelation',
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
			editValue: this.value ? parseInt(this.value) : null,
		}
	},

	computed: {
		...mapState(useTablesStore, ['activeTable', 'activeView']),
		allRelations() {
			const dataStore = useDataStore()
			return dataStore.getRelations(this.column.id) || {}
		},
		currentOption() {
			if (!this.value) {
				return null
			}
			return this.allRelations[this.value]
		},
		relationLabel() {
			return this.currentOption ? this.currentOption.label : null
		},
		relationOptions() {
			const activeElement = this.activeView || this.activeTable
			if (activeElement && !this.loading) {
				return Object.values(this.allRelations || {})
			}
			return []
		},
		isDeleted() {
			return !!this.value && !this.currentOption
		},
	},

	watch: {
		isEditing(isEditing) {
			if (isEditing) {
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

		async saveChanges() {
			if (this.localLoading) {
				return
			}

			const success = await this.updateCellValue(this.editValue)

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
.cell-relation {
	width: 100%;

	.non-edit-mode {
		cursor: pointer;
		min-height: 20px;
	}

	.deleted {
		opacity: 0.6;
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

.cursor-help {
	cursor: help;
}
</style>
