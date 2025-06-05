<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-progress" @click="startEditing">
		<div v-if="!isEditing" class="progress-display">
			<NcProgressBar v-if="getValue !== null" :value="getValue" />
			<div v-else class="empty-progress-placeholder">
				({{ t('tables', 'No progress set') }})
			</div>
		</div>
		<div v-else class="inline-editing-container">
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
			<div v-if="localLoading" class="icon-loading-small icon-loading-inline" />
		</div>
	</div>
</template>

<script>
import { NcProgressBar } from '@nextcloud/vue'
import cellEditMixin from '../mixins/cellEditMixin.js'

export default {
	name: 'TableCellProgress',

	components: {
		NcProgressBar,
	},

	mixins: [cellEditMixin],

	props: {
		value: {
			type: Number,
			default: null,
		},
	},

	computed: {
		getValue() {
			if (this.value !== null && !isNaN(this.value)) {
				return this.value
			}
			return null
		},
	},

	methods: {
		async saveChanges() {
			// Prevent multiple executions of saveChanges
			if (this.localLoading) {
				return
			}

			const newValue = Number(this.editValue)
			if (newValue === this.value || isNaN(newValue)) {
				this.isEditing = false
				return
			}

			// Ensure value is between 0 and 100
			const clampedValue = Math.min(Math.max(newValue, 0), 100)

			const success = await this.updateCellValue(clampedValue)

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
.cell-progress {
    padding-right: 10px;
    min-width: 12vw;
    cursor: pointer;
}

.progress-display {
    width: 100%;
    min-height: 20px;
}

.empty-progress-placeholder {
    color: var(--color-text-maxcontrast);
    font-style: italic;
    font-size: 0.9em;
    padding: 4px 0;
}

.cell-input {
    text-align: right;
    width: 100%;
}
</style>
