<template>
	<div>
		<a :href="getValue" target="_blank">{{ getValue | truncate(40) }}</a>
	</div>
</template>

<script>
import generalHelper from '../../../mixins/generalHelper.js'

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

	mixins: [generalHelper],

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
			if (this.hasJsonStructure(this.value)) {
				const valueObject = JSON.parse(this.value)
				return valueObject.resourceUrl || valueObject.title
			} else {
				return this.value
			}
		},
	},

}
</script>

<style lang="scss" scoped>

div {
	// min-width: 80px;
}

</style>
