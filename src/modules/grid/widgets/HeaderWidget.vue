<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="header-widget" :class="'header-widget--' + color" :style="style">
		<component :is="'h' + widgetHeadingLevel" v-if="settings.heading || editable" class="header-widget__title">
			{{ settings.heading || t('tables', 'Untitled header') }}
		</component>
		<p v-if="settings.subheading" class="header-widget__subtitle">
			{{ settings.subheading }}
		</p>
	</div>
</template>

<script>
// Start and end follow the reading direction
const TEXT_ALIGN = ['start', 'center', 'end']
// Color pairs of the theme, so the text stays readable in light, dark and high contrast
const COLORS = ['primary', 'neutral', 'success', 'warning', 'error']

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
			return {
				textAlign: TEXT_ALIGN.includes(this.settings.textAlign) ? this.settings.textAlign : 'start',
			}
		},
		color() {
			return COLORS.includes(this.settings.color) ? this.settings.color : 'primary'
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

	&--neutral {
		background-color: var(--color-background-dark);
		color: var(--color-main-text);
	}

	// A tint of the status color with the main text, as note cards do
	@each $status in success, warning, error {
		&--#{$status} {
			background-color: color-mix(in srgb, var(--color-#{$status}) 15%, var(--color-main-background));
			color: var(--color-main-text);
			border-inline-start: 4px solid var(--color-#{$status});
		}
	}

	&__title {
		margin: 0;
	}

	&__subtitle {
		margin: var(--default-grid-baseline) 0 0;
	}
}
</style>
