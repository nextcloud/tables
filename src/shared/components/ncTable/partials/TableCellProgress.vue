<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-progress" @dblclick="startEditing">
		<div v-if="!isEditing">
			<NcProgressBar v-if="getValue !== null" :value="getValue" />
		</div>
		<div v-else class="editing-container">
			<input
				ref="input"
				v-model.number="editValue"
				type="number"
				min="0"
				max="100"
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
import { NcProgressBar } from '@nextcloud/vue'

export default {
	name: 'TableCellProgress',

	components: {
		NcProgressBar,
	},

	props: {
		column: {
			type: Object,
			required: true,
		},
		rowId: {
			type: Number,
			required: true,
		},
		value: {
			type: Number,
			default: null,
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

		getValue() {
			if (this.value !== null && !isNaN(this.value)) {
				return this.value
			}
			return null
		},
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
			const newValue = Number(this.editValue)
			if (newValue === this.value || isNaN(newValue)) {
				this.isEditing = false
				return
			}

			// Ensure value is between 0 and 100
			const clampedValue = Math.min(Math.max(newValue, 0), 100)

			this.localLoading = true

			const data = [{
				columnId: this.column.id,
				value: clampedValue,
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
				this.$emit('update:value', clampedValue)
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
.cell-progress {
    padding-right: 10px;
    min-width: 12vw;
}
</style>
