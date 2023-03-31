<template>
	<table v-if="column" style="width: 100%;">
		<tr>
			<td>{{ t('tables', 'Default') }}</td><td class="align-right">
				{{ getLabelForDefault }}
			</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'SelectionMultiTableDisplay',
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	computed: {
		getLabelForDefault() {
			const i = this.column.selectionOptions?.findIndex((obj) => obj.id === this.getDefaultValue)
			return this.getSelectionOptions[i].label
		},
		getDefaultValue() {
			return parseInt(this.column.selectionDefault)
		},
		getSelectionOptions() {
			return this.column.selectionOptions
		},
	},
}
</script>
<style scoped>

table td {
	padding-right: 10px;
}

</style>
