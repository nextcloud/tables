<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-editor">
		<div v-if="!isEditing" @click="startEditing">
			<NcEditor v-if="value !== '' && value !== null"
				:can-edit="false"
				:text="value"
				:show-border="false"
				:show-readonly-bar="false" />
		</div>
		<div v-else
			ref="editingContainer"
			tabindex="0"
			@keydown.enter="saveChanges"
			@keydown.escape="cancelEdit">
			<NcEditor
				:can-edit="true"
				:text.sync="editValue"
				:show-readonly-bar="false" />
			<div v-if="localLoading" class="loading-indicator">
				<div class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import NcEditor from '../../ncEditor/NcEditor.vue'
import cellEditMixin from '../mixins/cellEditMixin.js'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TableCellEditor',

	components: {
		NcEditor
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
			type: String,
			default: '',
		},
	},

	watch: {
		isEditing(newValue) {
			if (newValue) {
				this.$nextTick(() => {
					// Add click outside listener
					document.addEventListener('click', this.handleClickOutside)
				})
			} else {
				// Remove click outside listener
				document.removeEventListener('click', this.handleClickOutside)
			}
		},
	},

	methods: {
		t,
		async saveChanges() {
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

		handleClickOutside(event) {
			// Check if the click is outside the editing container
			if (this.$refs.editingContainer && !this.$refs.editingContainer.contains(event.target)) {
				this.saveChanges()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.cell-editor {
	width: 100%;
}

.cell-editor > div {
	cursor: pointer;
}

.inline-editing-container {
	display: flex;
	flex-direction: column;
}

.editor-buttons {
	display: flex;
	gap: 8px;
	margin-top: 8px;
	align-items: center;
}

div {
	max-width: 670px;
	max-height: calc(var(--default-line-height) * 6);
	overflow-y: hidden;
	min-width: 100px;
	margin-top: calc(var(--default-grid-baseline) * 2);
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

:deep(.text-editor__wrapper div.ProseMirror) {
	padding: 0px 0px 0px 0px;
}

:deep(div[contenteditable='false']) {
	background: transparent;
	color: var(--color-main-text);
	width: auto;
	min-height: auto;
	opacity: 1;
	font-size: var(--default-font-size);
}

:deep(.editor__content) {
	max-width: 100% !important;
}
</style>
