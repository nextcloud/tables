<template>
	<div class="wrapper">
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
			if (this.value !== null) {
				return this.value
			}
			if (this.column && this.column.numberDefault) {
				return this.column.numberDefault
			} else {
				return null
			}
		},
	},
}
</script>

<style scoped>

.wrapper {
  padding-right: 10px;
}

</style>
