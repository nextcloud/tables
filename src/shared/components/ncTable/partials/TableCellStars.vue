<template>
	<div>
		{{ getValue }}
	</div>
</template>

<script>

export default {
	name: 'TableCellStars',
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
			default: 0,
		},
	},
	computed: {
		getValue() {
			const starEmpty = '☆'
			const starFull = '★'
			const v = this.value
			return starFull.repeat(v) + starEmpty.repeat(5-v)
		},
	},
}
</script>

<style scoped>

.wrapper {
  padding-right: 10px;
}

</style>
