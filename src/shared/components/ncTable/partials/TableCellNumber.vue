<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-number">
		<div v-if="!isEditing" @dblclick="startEditing">
			{{ column.numberPrefix }}{{ getValue }}{{ column.numberSuffix }}
		</div>
		<div v-else class="editing-container">
			<input
				ref="input"
				v-model="editValue"
				type="number"
				:min="getMin"
				:max="getMax"
				:step="getStep"
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
	name: 'TableCellNumber',

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
			if (this.value === null) {
				return null
			}
			return this.value.toFixed(this.column?.numberDecimals)
		},

		getStep() {
			return Math.pow(10, -(this.column?.numberDecimals || 0))
		},
		getMin() {
			if (this.column?.numberMin !== undefined) {
				return this.column.numberMin
			} else {
				return null
			}
		},
		getMax() {
			if (this.column?.numberMax !== undefined) {
				return this.column.numberMax
			} else {
				return null
			}
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
			if (Number(this.editValue) === this.value) {
				this.isEditing = false
				return
			}

			this.localLoading = true

			const data = [{
				columnId: this.column.id,
				value: this.editValue === '' ? null : Number(this.editValue),
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
				this.$emit('update:value', Number(this.editValue))
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
.cell-number {
    width: 100%;
    text-align: right;
}
</style>
