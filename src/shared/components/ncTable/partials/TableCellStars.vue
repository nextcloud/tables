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
			let res = ''
			if (v && v === 1) {
				res = starFull + starEmpty + starEmpty + starEmpty + starEmpty
			} else if (v && v === 2) {
				res = starFull + starFull + starEmpty + starEmpty + starEmpty
			} else if (v && v === 3) {
				res = starFull + starFull + starFull + starEmpty + starEmpty
			} else if (v && v === 4) {
				res = starFull + starFull + starFull + starFull + starEmpty
			} else if (v && v === 5) {
				res = starFull + starFull + starFull + starFull + starFull
			} else {
				res = starEmpty + starEmpty + starEmpty + starEmpty + starEmpty
			}
			return res
		},
	},
}
</script>

<style scoped>

.wrapper {
  padding-right: 10px;
}

</style>
