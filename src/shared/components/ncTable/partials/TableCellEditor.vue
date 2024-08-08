<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcEditor v-if="value !== '' && value !== null"
		:can-edit="false"
		:text="value"
		:show-border="false"
		:show-readonly-bar="false" />
</template>

<script>
import NcEditor from '../../ncEditor/NcEditor.vue'

export default {
	name: 'TableCellEditor',

	components: {
		NcEditor,
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
}
</script>

<style lang="scss" scoped>

div {
	max-width: 670px;
	max-height: calc(var(--default-line-height) * 6);
	overflow-y: scroll;
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
