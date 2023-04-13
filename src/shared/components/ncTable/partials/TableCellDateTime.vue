<template>
	<div>
		{{ getValue }}
	</div>
</template>

<script>
import Moment from '@nextcloud/moment'

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
			if (!this.value || this.value === 'none' || this.value === 'today') {
				return ''
			}

			if (!this.column.subtype) {
				return this.datetimeFormatter(this.value)
			} else if (this.column.subtype === 'time') {
				return this.datetimeTimeFormatter(this.value)
			} else if (this.column.subtype === 'date') {
				return this.datetimeDateFormatter(this.value)
			}
			return null
		},
		getDefaultValue() {
			const colDefault = this.column?.datetimeDefault || null
			if (colDefault) {
				if (!this.column.subtype) {
					return this.datetimeFormatter(colDefault)
				} else if (this.column.subtype === 'time') {
					return this.datetimeTimeFormatter(colDefault)
				} else if (this.column.subtype === 'date') {
					return this.datetimeDateFormatter(colDefault)
				}
			}
			return ''
		},
	},
	methods: {
		datetimeFormatter(time) {
			return Moment(time, 'YYYY-MM-DD HH:mm:ss').format('lll')
		},
		datetimeDateFormatter(time) {
			return Moment(time, 'YYYY-MM-DD HH:mm:ss').format('ll')
		},
		datetimeTimeFormatter(time) {
			return Moment(time, 'HH:mm:ss').format('LT')
		},
	},
}
</script>

<style lang="scss" scoped>

div {
  text-align: right;
}

</style>
