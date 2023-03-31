<template>
	<div>
		{{ getLabel }}
	</div>
</template>

<script>

export default {
	name: 'TableCellSelection',

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
		getLabel() {
			const i = this.column?.selectionOptions?.findIndex((obj) => obj.id === this.value)
			return this.column?.selectionOptions[i]?.label || null
		},
	},
}
</script>
