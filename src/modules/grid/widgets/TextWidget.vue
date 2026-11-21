<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="text-widget">
		<p v-for="(paragraph, index) in paragraphs" :key="index" class="text-widget__paragraph">
			{{ paragraph }}
		</p>
		<p v-if="paragraphs.length === 0 && editable" class="text-widget__empty">
			{{ t('tables', 'No description yet') }}
		</p>
	</div>
</template>

<script>
export default {
	name: 'TextWidget',
	props: {
		settings: {
			type: Object,
			required: true,
		},
		// Placeholders that prompt for content only show while the page is edited
		editable: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		paragraphs() {
			return String(this.settings.text ?? '')
				.split(/\n\s*\n/)
				.map(paragraph => paragraph.trim())
				.filter(paragraph => paragraph !== '')
		},
	},
}
</script>

<style lang="scss" scoped>
.text-widget {
	padding: calc(3 * var(--default-grid-baseline)) calc(4 * var(--default-grid-baseline));

	&__paragraph {
		margin: 0 0 calc(2 * var(--default-grid-baseline));
		white-space: pre-wrap;
	}

	&__empty {
		color: var(--color-text-maxcontrast);
	}
}
</style>
