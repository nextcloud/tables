<template>
	<div>
		<a :href="value" target="_blank">{{ value }}</a>
	</div>
</template>

<script>

export default {
	name: 'TableCellLink',
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

<style scoped>

.wrapper {
  padding-right: 10px;
}

</style>
