<template>
	<NcEditor v-if="value !== '' && value !== null"
		:can-edit="false"
		:text="value"
		:show-border="false"
		:show-readonly-bar="false" />
</template>

<script>
import NcEditor from '../../ncEditor/NcEditor.vue'

export default {
	name: 'TableCellEditor',

	components: {
		NcEditor,
	},

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
			default: '',
		},
	},
}
</script>

<style scoped>

div {
	max-height: calc(var(--default-line-height) * 6);
	overflow-y: scroll;
	min-width: 100px;
	white-space: pre-wrap;
	margin-top: calc(var(--default-grid-baseline) * 2);
	margin-bottom: calc(var(--default-grid-baseline) * 2);
}

</style>
