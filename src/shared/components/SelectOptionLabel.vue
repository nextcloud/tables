<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span class="select-option-label" :title="label">{{ label }}</span>
</template>

<script>
/**
 * The label of an NcSelect option as one text, cut off at the end. The default option of
 * NcSelect splits a long label into two parts, which screen readers and accessible names
 * read with a space in the middle ("Table o r view").
 */
export default {
	name: 'SelectOptionLabel',
	props: {
		label: {
			type: String,
			default: '',
		},
	},
}
</script>

<style scoped>
.select-option-label {
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
