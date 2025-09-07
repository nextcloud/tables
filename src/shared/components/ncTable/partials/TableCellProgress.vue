<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-progress" :style="{ opacity: !canEditCell() ? 0.6 : 1 }" @click="startEditingProgress">
		<div v-if="!isEditing" class="progress-display">
			<NcProgressBar :value="getValue" />
		</div>
		<div v-else class="inline-editing-container">
			<input ref="input" v-model="editValue" type="number" min="0" max="100" :disabled="localLoading"
				class="cell-input" @blur="saveChanges" @keyup.enter="saveChanges" @keyup.esc="cancelEdit">
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

	data() {
		return {
			editValue: this.value !== null ? this.value : 0,
		}
	},

	computed: {
		getValue() {
			if (this.value !== null && !isNaN(this.value)) {
				return this.value
			}
			return 0
		},
	},

	methods: {
		startEditingProgress() {
			if (!this.canEditCell()) {
				return false
			}
			this.isEditing = true
			this.$nextTick(() => {
				this.$refs.input?.focus()
			})
		},

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
	padding-inline-end: 10px;
	min-width: 12vw;
	cursor: pointer;

	div {
		min-height: 20px;
        display: flex;
        align-items: center;
	}
}

.progress-display {
	width: 100%;
	min-height: 20px;
	cursor: pointer;
}

.empty-progress-placeholder {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	font-size: 0.9em;
	padding: 4px 0;
}

.cell-input {
	text-align: end;
	width: 100%;
}
</style>
