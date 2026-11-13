<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="header-widget" :style="style">
		<component :is="'h' + widgetHeadingLevel" v-if="settings.heading || editable" class="header-widget__title">
			{{ settings.heading || t('tables', 'Untitled header') }}
		</component>
		<p v-if="settings.subheading" class="header-widget__subtitle">
			{{ settings.subheading }}
		</p>
	</div>
</template>

<script>
import { isHexColor } from '../../../shared/utils/gridColors.js'

// Start and end follow the reading direction
const TEXT_ALIGN = ['start', 'center', 'end']

export default {
	name: 'HeaderWidget',
	inject: {
		widgetHeadingLevel: { default: 2 },
	},
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
		style() {
			const style = {
				textAlign: TEXT_ALIGN.includes(this.settings.textAlign) ? this.settings.textAlign : 'start',
			}
			if (isHexColor(this.settings.backgroundColor)) {
				style.backgroundColor = this.settings.backgroundColor
			}
			if (isHexColor(this.settings.textColor)) {
				style.color = this.settings.textColor
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
