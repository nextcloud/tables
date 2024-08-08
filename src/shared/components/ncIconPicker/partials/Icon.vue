<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcLoadingIcon v-if="loading"
		:name="t('tables', 'Icon {iconName} loading', { iconName: name})"
		:size="30" />
	<NcButton v-else
		class="icon-picker__icon"
		type="tertiary"
		:aria-label="t('tables', 'Select icon for the application')"
		@click.prevent="select">
		<template #icon>
			<NcIconSvgWrapper :svg="icon" :size="30" />
		</template>
	</NcButton>
</template>

<script>
import { NcLoadingIcon, NcIconSvgWrapper, NcButton } from '@nextcloud/vue'
import svgHelper from '../mixins/svgHelper.js'

export default {
	name: 'Icon',

	components: {
		NcLoadingIcon,
		NcIconSvgWrapper,
		NcButton,
	},
	mixins: [svgHelper],

	props: {
		name: {
			type: String,
			default: null,
		},
	},

	emits: ['select'],

	data() {
		return {
			loading: true,
			icon: null,
		}
	},

	async mounted() {
		this.icon = await this.getContextIcon(this.name)
		this.loading = false
	},

	methods: {
		select() {
			this.$emit('select', this.name)
		},
	},
}
</script>

<style>
.icon-picker__icon {
	border-radius: 15px !important;
	padding: 8px !important;
	width: 30px !important;
	height: 30px !important;
	margin: 2px !important;
	border: 2px solid transparent !important;

	&:hover {
		border: 2px solid var(--color-primary-element) !important;
	}
}
</style>
