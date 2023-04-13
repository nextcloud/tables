<template>
	<div>
		<ul>
			<li v-for="v in getObjectsForSelectionMulti(value, column)" :key="v.id">
				{{ v.label }}<span v-if="v.deleted" :title="t('tables', 'This option is outdated.')">&nbsp;⚠️</span>
			</li>
		</ul>
	</div>
</template>

<script>
import selectionMultiMixin from '../mixins/columnsTypes/selectionMultiMixin.js'

export default {
	name: 'TableCellMultiSelection',

	mixins: [selectionMultiMixin],

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

}
</script>
<style lang="scss" scoped>

ul {
	list-style-type: square;
	padding-left: calc(var(--default-grid-baseline) * 3);
}

</style>
