<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="header-widget" :style="style">
		<h2 class="header-widget__title">
			{{ content.title || t('tables', 'Untitled header') }}
		</h2>
		<p v-if="content.subtitle" class="header-widget__subtitle">
			{{ content.subtitle }}
		</p>
	</div>
</template>

<script>
import { isHexColor } from '../../../shared/utils/gridColors.js'

export default {
	name: 'HeaderWidget',
	props: {
		content: {
			type: Object,
			required: true,
		},
	},
	computed: {
		style() {
			const style = {
				textAlign: ['left', 'center', 'right'].includes(this.content.textAlign) ? this.content.textAlign : 'left',
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
	padding: calc(4 * var(--default-grid-baseline, 4px)) calc(5 * var(--default-grid-baseline, 4px));
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);

	&__title {
		margin: 0;
		font-size: 24px;
		font-weight: bold;
		line-height: 1.3;
	}

	&__subtitle {
		margin: calc(1 * var(--default-grid-baseline, 4px)) 0 0;
		opacity: 0.85;
	}
}
</style>
