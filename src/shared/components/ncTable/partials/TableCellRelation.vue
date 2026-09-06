<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-relation">
		<div v-if="!isEditing" class="non-edit-mode" @click="handleStartEditing">
			<div v-if="hasDeletedRelations" class="relation-labels">
				<span v-for="(entry, index) in displayEntries" :key="`${entry.id}-${index}`" class="relation-label">
					<template v-if="entry.deleted">
						<span class="deleted">{{ entry.id }}</span>
						<span class="cursor-help" :title="t('tables', 'This relation does not exist anymore.')">&nbsp;⚠️</span>
					</template>
					<template v-else>
						{{ entry.label }}
					</template>
					<span v-if="index < displayEntries.length - 1">, </span>
				</span>
			</div>
			<div v-else class="relation-labels">
				{{ relationLabels }}
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
				:multiple="allowMultiple"
				:close-on-select="!allowMultiple"
				:aria-label-combobox="t('tables', 'Select relation value')"
				:disabled="localLoading || !canEditCell()"
				style="width: 100%;"
				data-cy="relationCellSelect" />
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
			type: [Number, Array],
			default: null,
		},
	},

	data() {
		return {
			isInitialEditClick: false,
			editValue: null,
		}
	},

	computed: {
		...mapState(useTablesStore, ['activeTable', 'activeView']),
		allowMultiple() {
			return !!this.column.customSettings?.allowMultiple
		},
		valueIds() {
			if (this.value === null || this.value === undefined || this.value === '') {
				return []
			}
			const list = Array.isArray(this.value) ? this.value : [this.value]
			return list.map(id => parseInt(id)).filter(id => !Number.isNaN(id))
		},
		allRelations() {
			const dataStore = useDataStore()
			return dataStore.getRelations(this.column.id) || {}
		},
		displayEntries() {
			return this.valueIds.map(id => {
				const option = this.allRelations[id]
				return option
					? { id, label: option.label, deleted: false }
					: { id, label: null, deleted: true }
			})
		},
		relationLabels() {
			return this.displayEntries
				.filter(entry => !entry.deleted)
				.map(entry => entry.label)
				.join(', ')
		},
		hasDeletedRelations() {
			return this.displayEntries.some(entry => entry.deleted)
		},
		relationOptions() {
			const activeElement = this.activeView || this.activeTable
			if (activeElement) {
				return Object.values(this.allRelations || {}).map(option => ({ ...option, id: parseInt(option.id) }))
			}
			return []
		},
	},

	watch: {
		isEditing(isEditing) {
			if (isEditing) {
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
			event.stopPropagation()
		},

		startEditing() {
			if (!this.canEditCell()) {
				return false
			}
			this.isEditing = true
			const ids = [...this.valueIds]
			this.editValue = this.allowMultiple ? ids : (ids[0] ?? null)
			this.$nextTick(() => {
				this.$refs.editingContainer?.focus()
			})
		},

		async saveChanges() {
			if (this.localLoading) {
				return
			}

			let newValue = this.editValue === null || this.editValue === undefined
				? []
				: (Array.isArray(this.editValue) ? this.editValue : [this.editValue])
			newValue = newValue.map(id => parseInt(id)).filter(id => !Number.isNaN(id))

			if (!this.allowMultiple) {
				newValue = newValue.slice(0, 1)
			}

			const success = await this.updateCellValue(newValue)

			if (!success) {
				this.cancelEdit()
			}

			this.localLoading = false
			this.isEditing = false
		},

		handleClickOutside(event) {
			if (this.isInitialEditClick) {
				this.isInitialEditClick = false
				return
			}

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
