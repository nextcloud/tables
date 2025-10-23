<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-text-line" :style="{ opacity: !canEditCell() ? 0.6 : 1 }">
		<div v-if="!isEditing" @click="startEditing">
			{{ value || '' }}
		</div>
		<div v-else class="inline-editing-container">
			<NcTextField ref="input" v-model="editValue" :aria-label="t('tables', 'Cell input')" :disabled="localLoading || !canEditCell()" class="cell-input"
				@keyup.enter="saveChanges" @keyup.esc="cancelEdit" @blur="saveChanges" />
			<div v-if="localLoading" class="icon-loading-small icon-loading-inline" />
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

	mixins: [cellEditMixin],

	props: {
		value: {
			type: String,
			default: '',
		},
	},

	methods: {
		async saveChanges() {
			// needed for properly saving on Enter key press
			if (this.localLoading) {
				return
			}

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

.cell-text-line > div {
    min-height: 20px;
    cursor: pointer;
}

.inline-editing-container {
    display: flex;
    align-items: center;
}

:deep(.input-field__input:not(:focus)) {
    border: 1px solid var(--color-main-background);
}
</style>
