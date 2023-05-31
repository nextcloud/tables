<template>
	<div>
		<ul>
			<li v-for="v in getObjects()" :key="v.id">
				{{ v.label }}<span v-if="v.deleted" :title="t('tables', 'This option is outdated.')">&nbsp;⚠️</span>
			</li>
		</ul>
	</div>
</template>

<script>

export default {
	name: 'TableCellMultiSelection',

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
			type: Array,
			default: null,
		},
	},
	methods: {
		getObjects() {
			return this.column.getObjects(this.value)
		},
	},

}
</script>
<style lang="scss" scoped>

ul {
	list-style-type: disc;
	padding-left: calc(var(--default-grid-baseline) * 3);
}

</style>
