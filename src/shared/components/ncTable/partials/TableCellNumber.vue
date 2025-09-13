<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-number" :style="{ opacity: !canEditCell() ? 0.6 : 1 }">
		<div v-if="!isEditing" class="number-display" @click="startEditing">
			{{ column.numberPrefix }}{{ getValue }}{{ column.numberSuffix }}
		</div>
		<div v-else class="inline-editing-container">
			<div v-if="column.numberPrefix" class="number-prefix">
				{{ column.numberPrefix }}
			</div>
			<input ref="input" v-model="editValue" type="number" :min="getMin" :max="getMax" :step="getStep"
				:disabled="localLoading || !canEditCell()" class="cell-input" @blur="saveChanges"
				@keyup.enter="saveChanges" @keyup.esc="cancelEdit">
			<div v-if="column.numberSuffix" class="number-suffix">
				{{ column.numberSuffix }}
			</div>
			<div v-if="localLoading" class="icon-loading-small icon-loading-inline" />
		</div>
	</div>
</template>

<script>
import cellEditMixin from '../mixins/cellEditMixin.js'

export default {
	name: 'TableCellNumber',

	mixins: [cellEditMixin],

	props: {
		value: {
			type: Number,
			default: null,
		},
	},

	computed: {
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
		async saveChanges() {
			// Prevent multiple executions of saveChanges
			if (this.localLoading) {
				return
			}

			if (Number(this.editValue) === this.value) {
				this.isEditing = false
				return
			}

			let newValue = this.editValue === '' ? null : Number(this.editValue)

			if (newValue !== null && !isNaN(newValue)) {
				if (this.getMin !== null && newValue < this.getMin) {
					newValue = this.getMin
				}
				if (this.getMax !== null && newValue > this.getMax) {
					newValue = this.getMax
				}
			}

			const success = await this.updateCellValue(newValue)

			if (!success) {
				this.cancelEdit()
			}

			this.localLoading = false
			this.isEditing = false
		},
	},
}
</script>

<style scoped>
.cell-number {
	width: 100%;
	text-align: end;

	div {
		min-height: 20px;
	}
}

.number-display {
	width: 100%;
	min-height: 20px;
	cursor: pointer;
}

.inline-editing-container {
	display: flex;
	align-items: center;
}

.cell-input {
	text-align: end;
	flex-grow: 1;
}

.number-prefix,
.number-suffix {
	padding: 0 4px;
}
</style>
