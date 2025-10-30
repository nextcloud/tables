<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="editingContainer"
		class="rich-editor-edit-mode"
		@keydown.escape.prevent="cancelEdit">
		<NcEditor
			:can-edit="true"
			:text.sync="localValue"
			:show-border="false"
			:show-readonly-bar="false"
			:show-menu-bar="false" />
		<div v-if="loading" class="loading-indicator">
			<div class="icon-loading-small icon-loading-inline" />
		</div>
	</div>
</template>

<script>
import NcEditor from '../../ncEditor/NcEditor.vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'RichEditor',

	components: {
		NcEditor,
	},

	props: {
		value: {
			type: String,
			default: '',
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			localValue: this.value || '',
			isInitialEditClick: false,
		}
	},

	watch: {
		value(newValue) {
			this.localValue = newValue || ''
		},
	},

	mounted() {
		// Add click outside listener after the current event loop
		// to avoid the same click that triggered editing from closing the editor
		this.$nextTick(() => {
			document.addEventListener('click', this.handleClickOutside)
		})
	},

	beforeDestroy() {
		document.removeEventListener('click', this.handleClickOutside)
	},

	methods: {
		t,

		notifyEditingStarted() {
			this.isInitialEditClick = true
		},

		handleClickOutside(event) {
			// Ignore the initial click that started editing
			if (this.isInitialEditClick) {
				this.isInitialEditClick = false
				return
			}

			// Check if the click is outside our editing container
			if (this.$refs.editingContainer && !this.$refs.editingContainer.contains(event.target)) {

				const isEditorRelated = event.target.closest('.text-editor__wrapper')
										|| event.target.closest('.text-menubar')
										|| event.target.closest('.ProseMirror')
										|| event.target.closest('[contenteditable]')
										|| event.target.closest('.text-editor')
										|| event.target.closest('.editor-wrapper')
										|| event.target.closest('.v-popper__popper') // For any tooltips/dropdowns
										|| event.target.closest('[role="dialog"]') // For any modal dialogs
										|| event.target.closest('.widgets--list') // For widgets like images, videos
										|| event.target.closest('.tippy-box') // For link preview
										|| event.target.closest('a') // For links
										|| event.target.closest('svg') // For icons

				if (!isEditorRelated) {
					this.$emit('save', this.localValue)
				}
			}
		},

		getValue() {
			return this.localValue
		},

		cancelEdit() {
			this.$emit('cancel')
		},
	},
}
</script>

<style lang="scss" scoped>
.rich-editor-edit-mode {
	position: relative;
	border: 2px solid var(--color-border-maxcontrast);
	min-height: 24px;

	:deep(.smart-picker-menu-container) {
		display: none;
	}
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
</style>
