<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-text-line">
		<div v-if="!isEditing && value" @click="startEditing">
			{{ value | truncate(50) }}
		</div>
		<div v-else class="inline-editing-container">
			<input
				ref="input"
				v-model="editValue"
				type="text"
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
import cellEditMixin from '../mixins/cellEditMixin.js'

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

	mixins: [cellEditMixin],

	props: {
		value: {
			type: String,
			default: '',
		},
	},

	methods: {
		async saveChanges() {
			if (this.editValue === this.value) {
				this.isEditing = false
				return
			}

			const newValue = this.editValue ?? ''
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
.cell-text-line {
    width: 100%;
}
</style>
