<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcButton :aria-label="ariaLabel" class="button-small" @click="submit">
		<div class="button-small__label">
			<slot name="icon" />
			<slot />
		</div>
	</NcButton>
</template>
<script>
import { NcButton } from '@nextcloud/vue'

export default {

	components: {
		NcButton,
	},

	props: {
		ariaLabel: {
		      type: String,
		      default: null,
		    },
	},

	methods: {
		submit() {
			this.$emit('click')
		},
	},

}
</script>
<style lang="scss" scoped>

	.button-small {
		min-height: auto !important;
		padding: 1px 2px !important;
		margin: 0 0 0 4px !important;
	}

	.button-small__label {
		display: flex;
		flex-flow: row nowrap;
		align-items: center;
	}

</style>
