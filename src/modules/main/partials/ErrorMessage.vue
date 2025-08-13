<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="error-container">
		<IconTables :size="64" style="margin-bottom: 1rem;" />
		<p>{{ message }}</p>
	</div>
</template>

<script>
import IconTables from '../../../shared/assets/icons/IconTables.vue'

export default {
	name: 'ErrorMessage',
	components: { IconTables },
	props: {
		message: {
			type: String,
			required: true,
		},
	},
}
</script>

<style lang="scss">
.error-container {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	padding: 2rem;
	height: 100dvh;
	min-height: 100%;
	color: var(--color-text);
	opacity: 0.6;

	p {
		font-size: clamp(1.2rem, 4vw, 2rem);
		font-weight: 600;
		max-width: 90%;
		word-wrap: break-word;
	}
}
</style>
