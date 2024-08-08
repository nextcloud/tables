<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="icon-picker__icons">
		<template v-for="icon in icons">
			<Icon :key="icon.name" :name="icon.name" @select="select" />
		</template>
	</div>
</template>

<script>
import Icon from './Icon.vue'

export default {
	name: 'IconPicker',

	components: {
		Icon,
	},

	props: {
		icons: {
			type: Array,
			default: null,
		},
		filterString: {
			type: String,
			default: '',
		},
	},

	emits: ['select'],

	methods: {
		select(icon) {
			this.$emit('select', icon)
		},
	},
}
</script>

<style>
.icon-picker__icons {
	max-width: 300px;
	max-height: 400px;
	display: grid;
	grid-template-columns: repeat(5, 1fr);
	overflow-y: scroll;
	padding: 10px;
}
</style>
