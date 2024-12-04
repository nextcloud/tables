<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<EditorContent :editor="editor" />
	</div>
</template>

<script>
import { Editor, EditorContent } from '@tiptap/vue-2'
import { StarterKit } from '@tiptap/starter-kit'

export default {
	name: 'TableCellHtml',

	components: {
		EditorContent,
	},

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
		}
	},

	watch: {
		value(value) {
			this.editor.commands.setContent(value, false)
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
		this.editor.destroy()
	},

}
</script>

<style scoped lang="scss">

:deep(.tiptap-reader-cell) {
	max-height: calc(var(--default-line-height) * 6);
	overflow-y: scroll;
	min-width: 100px;
	white-space: pre-wrap;
	margin-top: calc(var(--default-grid-baseline) * 2);
	margin-bottom: calc(var(--default-grid-baseline) * 2);

	li {
		display: flex;
		align-items: center;
	}

	li > div {
		padding-left: calc(var(--default-grid-baseline) * 2);
	}
}

</style>
