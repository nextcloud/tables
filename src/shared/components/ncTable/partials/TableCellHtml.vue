<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<!-- eslint-disable-next-line vue/no-v-html -->
		<div v-if="value" class="tiptap-reader-cell" v-html="value" />
	</div>
</template>

<script>
export default {
	name: 'TableCellHtml',

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
