<template>
	<div>
		<a :href="value" target="_blank">{{ value | truncate(40) }}</a>
	</div>
</template>

<script>

export default {
	name: 'TableCellLink',

	filters: {
		truncate(string, num) {
			if (string.length >= num) {
				return string.substring(0, num) + '...'
			} else {
				return string
			}
		},
	},

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
			type: String,
			default: null,
		},
	},

}
</script>

<style lang="scss" scoped>

div {
  // min-width: 80px;
}

</style>
