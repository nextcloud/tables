<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcProgressBar v-if="getValue !== null" :value="getValue" />
	</div>
</template>

<script>
import { NcProgressBar } from '@nextcloud/vue'

export default {
	name: 'TableCellProgress',
	components: { NcProgressBar },
	props: {
		column: {
			type: Object,
			default: () => {},
		},
		rowId: {
			type: Number,
			default: null,
		},
		value: {
			type: Number,
			default: null,
		},
	},
	computed: {
		getValue() {
			if (this.value !== null && !isNaN(this.value)) {
				return this.value
			}
			return null
		},
	},
}
</script>

<style scoped>

div {
	padding-right: 10px;
	min-width: 12vw;
}

</style>
