<template>
	<div v-if="value">
		{{ value }}
	</div>
</template>

<script>
export default {
	name: 'TableCell',
	props: {
		columnId: {
			type: Number,
			default: null,
		},
		rowId: {
			type: Number,
			default: null,
		},
		value: {
			type: String,
			default: '',
		},
	},
}
</script>

<style scoped>

</style>
