<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="header-widget" :style="style">
		<component :is="'h' + widgetHeadingLevel" v-if="content.title || editable" class="header-widget__title">
			{{ content.title || t('tables', 'Untitled header') }}
		</component>
		<p v-if="content.subtitle" class="header-widget__subtitle">
			{{ content.subtitle }}
		</p>
	</div>
</template>

<script>
import { isHexColor } from '../../../shared/utils/gridColors.js'

// Stored values say left and right; rendered as start and end so they follow the reading direction
const TEXT_ALIGN = { left: 'start', center: 'center', right: 'end' }

export default {
	name: 'HeaderWidget',
	inject: {
		widgetHeadingLevel: { default: 2 },
	},
	props: {
		content: {
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
		style() {
			const style = {
				textAlign: TEXT_ALIGN[this.content.textAlign] ?? 'start',
			}
			if (isHexColor(this.content.backgroundColor)) {
				style.backgroundColor = this.content.backgroundColor
			}
			if (isHexColor(this.content.textColor)) {
				style.color = this.content.textColor
			}
			return style
		},
	},
}
</script>

<style lang="scss" scoped>
.header-widget {
	display: flex;
	flex-direction: column;
	justify-content: center;
	height: 100%;
	padding: calc(4 * var(--default-grid-baseline)) calc(5 * var(--default-grid-baseline));
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);

	&__title {
		margin: 0;
	}

	&__subtitle {
		margin: var(--default-grid-baseline) 0 0;
	}
}
</style>
