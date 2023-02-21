<template>
	<!-- eslint-disable-next-line vue/no-v-html -->
	<div v-if="value" v-html="value" />
</template>

<script>
export default {
	name: 'TableCellHtml',

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
  overflow: auto;
  min-width: 100px;
  max-width: 30vw;
}

</style>
