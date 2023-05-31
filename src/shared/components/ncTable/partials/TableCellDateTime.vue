<template>
	<div>
		{{ getValue }}
	</div>
</template>

<script>

export default {
	name: 'TableCellDateTime',
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
	computed: {
		getValue() {
			// default value is for the form, if you want to present the date from today, you should use the calculating column
			if (!this.value || this.value === 'none' || this.value === 'today' || this.value === 'now') {
				return ''
			}
			return this.column.formatValue(this.value)
		},
		getDefaultValue() {
			return this.column.formatValue(this.column.datetimeDefault)
		},
	},
}
</script>

<style lang="scss" scoped>

div {
	text-align: right;
}

</style>
