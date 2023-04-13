<template>
	<div>
		{{ getLabelForSelection() }}<span v-if="isDeletedLabelForSelection()" :title="t('tables', 'This option is outdated.')">&nbsp;⚠️</span>
	</div>
</template>

<script>
import selectionMixin from '../mixins/columnsTypes/selectionMixin.js'

export default {
	name: 'TableCellSelection',

	mixins: [selectionMixin],

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
}
</script>

<style lang="scss" scoped>

span {
	cursor: help;
}

</style>
