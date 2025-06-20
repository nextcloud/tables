<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-text-line">
		<div class="inline-editing-container">
			<NcTextField v-model="editValue" :disabled="localLoading"
				@keyup.enter="saveChanges"
				@keyup.esc="cancelEdit"
				@blur="saveChanges" />
			<!--
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
			-->
		</div>
	</div>
</template>

<script>
import cellEditMixin from '../mixins/cellEditMixin.js'
import { NcTextField } from '@nextcloud/vue'

export default {
	name: 'TableCellTextLine',

	components: {
		NcTextField,
	},

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

	beforeMount() {
		this.editValue = this.value
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

:deep(.input-field__input:not(:focus)) {
	border: 1px solid var(--color-main-background);
}

</style>
