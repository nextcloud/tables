<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-text-line">
		<div v-if="!isEditing && value" @dblclick="startEditing">
			{{ value | truncate(50) }}
		</div>
		<div v-else class="editing-container">
			<input
				ref="input"
				v-model="editValue"
				type="text"
				:disabled="localLoading"
				class="cell-input"
				@blur="saveChanges"
				@keyup.enter="saveChanges"
				@keyup.esc="cancelEdit">
			<div v-if="localLoading" class="icon-loading-inline" />
		</div>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import { useDataStore } from '../../../../store/data.js'

export default {
	name: 'TableCellTextLine',

	filters: {
		truncate(string, num) {
			if (string?.length >= num) {
				return string.substring(0, num) + '...'
			} else {
				return string
			}
		},
	},

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
			type: String,
			default: '',
		},
	},

	data() {
		return {
			isEditing: false,
			editValue: '',
			localLoading: false,
		}
	},

	computed: {
		...mapState(useDataStore, {
			rowMetadata(state) {
				return state.getRowMetadata(this.rowId)
			},
		}),
	},

	methods: {
		...mapActions(useDataStore, ['updateRow']),

		startEditing() {
			this.editValue = this.value
			this.isEditing = true
			this.$nextTick(() => {
				this.$refs.input.focus()
			})
		},

		async saveChanges() {
			if (this.editValue === this.value) {
				this.isEditing = false
				return
			}

			this.localLoading = true

			const data = [{
				columnId: this.column.id,
				value: this.editValue ?? '',
			}]

			const res = await this.updateRow({
				id: this.rowId,
				isView: this.rowMetadata.isView,
				elementId: this.rowMetadata.elementId,
				data,
			})

			if (!res) {
				showError(t('tables', 'Could not update cell'))
				this.cancelEdit()
			} else {
				this.$emit('update:value', this.editValue)
			}

			this.localLoading = false
			this.isEditing = false
		},

		cancelEdit() {
			this.isEditing = false
			this.editValue = this.value
		},
	},
}
</script>

<style scoped>
.cell-text-line {
    width: 100%;
}
</style>
