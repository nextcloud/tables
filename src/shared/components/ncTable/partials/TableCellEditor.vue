<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-editor" :style="{ opacity: !canEditCell() ? 0.6 : 1 }">
		<div v-show="!isEditing" class="cell-display-mode" @click="handleStartEditing">
			<NcEditor v-if="value && value.trim()"
				:can-edit="false"
				:text="value"
				:show-border="false"
				:show-readonly-bar="false" />
		</div>
		<RichEditor v-show="isEditing"
			ref="richEditor"
			:value="value"
			:loading="localLoading"
			@save="saveChanges"
			@cancel="cancelEdit" />
	</div>
</template>

<script>
import NcEditor from '../../ncEditor/NcEditor.vue'
import RichEditor from './RichEditor.vue'
import cellEditMixin from '../mixins/cellEditMixin.js'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TableCellEditor',

	components: {
		NcEditor,
		RichEditor,
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

	data() {
		return {
		}
	},

	methods: {
		t,

		handleStartEditing(event) {
			// Don't start editing if clicking on widgets like images, links, link preview icon (svg)
			if (event.target.closest('.widgets--list') || event.target.closest('.ProseMirror-widget') || event.target.closest('.tippy-box') || event.target.closest('a') || event.target.closest('svg')) {
				return
			}

			this.startEditing()
			// Stop the event from propagating to avoid immediate click outside
			event.stopPropagation()
		},

		async saveChanges(newValue) {
			if (this.localLoading) return

			if (newValue === this.value) {
				this.isEditing = false
				return
			}

			const success = await this.updateCellValue(newValue || '')

			if (success) {
				this.isEditing = false
			} else {
				this.cancelEdit()
			}
			this.localLoading = false
		},

		cancelEdit() {
			this.isEditing = false
		},

		startEditing() {
			if (!this.canEditCell()) return false
			this.isEditing = true

			this.$nextTick(() => {
				if (this.$refs.richEditor) {
					this.$refs.richEditor.notifyEditingStarted()
				}
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.cell-editor {
	width: 100%;
	position: relative;
}

.cell-display-mode {
	cursor: pointer;
	min-height: 24px;
	border: 2px solid transparent;
	position: relative;

	:deep(.content-wrapper) {
		padding-left: 22px;
	}
}

.cell-display-mode,
:deep(.rich-editor-edit-mode) {
	width: 100%;
	min-height: 24px;
}

:deep(.text-editor__wrapper div.ProseMirror) {
	padding: 8px;
	min-height: 24px;
}

:deep(div[contenteditable='false']) {
	background: transparent;
	color: var(--color-main-text);
}
</style>
