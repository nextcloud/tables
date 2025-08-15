<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="editingContainer"
		class="rich-editor-edit-mode"
		@keydown.escape.prevent="canceEdit">
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
			ignoreNextClick: true,
		}
	},

	watch: {
		value(newValue) {
			this.localValue = newValue || ''
		},
	},

	mounted() {
		// Add a small delay to prevent the initial click from immediately triggering save
		setTimeout(() => {
			document.addEventListener('click', this.handleClickOutside)
			this.ignoreNextClick = false
		}, 200)
	},

	beforeDestroy() {
		document.removeEventListener('click', this.handleClickOutside)
	},

	methods: {
		t,

		handleClickOutside(event) {
			// Ignore the first click to prevent immediate save after starting edit
			if (this.ignoreNextClick) {
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

				if (!isEditorRelated) {
					this.$emit('save', this.localValue)
				}
			}
		},

		getValue() {
			return this.localValue
		},

		canceEdit() {
			this.$emit('cancel')
		},
	},
}
</script>

<style lang="scss" scoped>
.rich-editor-edit-mode {
	position: relative;
	border: 1px solid var(--color-border-maxcontrast);
}

.loading-indicator {
	position: absolute;
	top: 4px;
	right: 4px;
}

:deep(.text-editor__wrapper div.ProseMirror) {
	padding: 8px;
	min-height: 24px;
}
</style>
