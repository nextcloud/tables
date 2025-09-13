<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-editor tables-tiptap-wrapper" :style="{ opacity: !canEditCell() ? 0.6 : 1 }">
		<div v-if="!isEditing" @click="handleStartEditing">
			<EditorContent :editor="editor" />
		</div>
		<div v-else
			ref="editingContainer"
			class="tiptap-edit-mode"
			@keydown.escape.prevent="cancelEdit">
			<TiptapMenuBar
				:value.sync="localValue"
				:text-length-limit="getTextLimit"
				@input="updateText" />
			<div v-if="localLoading" class="loading-indicator">
				<div class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import { Editor, EditorContent } from '@tiptap/vue-2'
import { StarterKit } from '@tiptap/starter-kit'
import TiptapMenuBar from './TiptapMenuBar.vue'
import cellEditMixin from '../mixins/cellEditMixin.js'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TableCellHtml',

	components: {
		EditorContent,
		TiptapMenuBar,
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
			editor: null,
			localValue: '',
			isInitialEditClick: false,
		}
	},

	computed: {
		getTextLimit() {
			if (this.column.textMaxLength === -1) {
				return null
			} else {
				return this.column.textMaxLength
			}
		},
	},

	watch: {
		value(value) {
			if (this.editor) {
				this.editor.commands.setContent(value, false)
			}
		},
	},

	mounted() {
		this.editor = new Editor({
			extensions: [
				StarterKit,
			],
			content: this.value,
			editable: false,
		})
	},

	beforeUnmount() {
		if (this.editor) {
			this.editor.destroy()
		}
	},

	methods: {
		t,

		handleStartEditing(event) {
			// Don't start editing if clicking on links
			if (event.target.closest('a')) {
				return
			}
			this.startEditing()
			event.stopPropagation()
		},

		handleClickOutside(event) {
			if (!this.isEditing) return

			if (this.isInitialEditClick) {
				this.isInitialEditClick = false
				return
			}

			// Check if the click is outside our editing container
			if (this.$refs.editingContainer && !this.$refs.editingContainer.contains(event.target)) {
				const isEditorRelated = event.target.closest('.tables-tiptap-wrapper')
										|| event.target.closest('.ProseMirror')
										|| event.target.closest('[contenteditable]')
										|| event.target.closest('.text-menubar')
										|| event.target.closest('.text-editor')
										|| event.target.closest('.editor-wrapper')
										|| event.target.closest('[role="dialog"]')
										|| event.target.closest('[role="menu"]')
										|| event.target.closest('[role="listbox"]')

				if (!isEditorRelated) {
					this.saveChanges()
				}
			}
		},

		updateText(text) {
			this.localValue = text
		},

		async saveChanges() {
			if (this.localLoading) return

			if (this.localValue === this.value) {
				this.stopEditing()
				return
			}

			const success = await this.updateCellValue(this.localValue || '')

			if (success) {
				this.stopEditing()
			} else {
				this.cancelEdit()
			}
			this.localLoading = false
		},

		cancelEdit() {
			this.localValue = this.value
			this.stopEditing()
		},

		startEditing() {
			if (!this.canEditCell()) return false
			this.localValue = this.value || ''
			this.isEditing = true
			this.isInitialEditClick = true

			document.addEventListener('click', this.handleClickOutside, true)
		},

		stopEditing() {
			this.isEditing = false
			this.isInitialEditClick = false
			document.removeEventListener('click', this.handleClickOutside, true)
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
	min-height: 24px;
}

.tiptap-edit-mode {
	position: relative;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	cursor: default;
}

.loading-indicator {
	position: absolute;
	top: 4px;
	inset-inline-end: 4px;
}

:deep(.text-editor__wrapper div.ProseMirror) {
	padding: 8px;
	min-height: 24px;
}

:deep(div[contenteditable='false']) {
	background: transparent;
	color: var(--color-main-text);
	width:100%;
	opacity: 1;

	&:hover {
		background: var(--color-background-hover);
	}
}

:deep(.tables-tiptap-wrapper) {
	.menuBar {
		padding: 8px;
		border-bottom: 1px solid var(--color-border);
		background: var(--color-background-dark);
		width: 100%;
	}

	.ProseMirror {
		padding: 8px;
		min-height: 60px;
		outline: none;
		border: none !important;
		border-color: none !important;
		box-shadow: none !important;
	}

	.character-count {
		padding: 4px 8px;
		font-size: 12px;
		color: var(--color-text-maxcontrast);
		border-top: 1px solid var(--color-border);
		background: var(--color-background-dark);
	}
}
</style>
