<template>
	<div>
		{{ value }}
	</div>
</template>

<script>

export default {
	name: 'TableCellYesNo',

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
			type: Boolean,
			default: false,
		},
	},
}
</script>
