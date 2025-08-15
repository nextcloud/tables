<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-editor">
		<div v-if="!isEditing" @click="startEditing">
			<NcEditor v-if="value && value.trim()"
				:can-edit="false"
				:text="value"
				:show-border="false"
				:show-readonly-bar="false" />
		</div>
		<RichEditor v-else
			:value="value"
			:loading="isSaving"
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
			isSaving: false,
		}
	},

	methods: {
		t,

		async saveChanges(newValue) {
			if (this.isSaving) return

			if (newValue === this.value) {
				this.isEditing = false
				return
			}

			this.isSaving = true
			const success = await this.updateCellValue(newValue || '')
			this.isSaving = false

			if (success) {
				this.isEditing = false
			} else {
				this.cancelEdit()
			}
		},

		cancelEdit() {
			this.isEditing = false
		},

		startEditing() {
			if (!this.canEditCell()) return false
			this.isEditing = true
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

:deep(.text-editor__wrapper div.ProseMirror) {
	padding: 8px;
	min-height: 24px;
}

:deep(div[contenteditable='false']) {
	background: transparent;
	color: var(--color-main-text);
}
</style>
