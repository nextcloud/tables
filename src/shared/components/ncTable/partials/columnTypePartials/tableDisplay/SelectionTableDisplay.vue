<template>
	<table v-if="column" style="width: 100%;">
		<tr>
			<td>{{ t('tables', 'Default') }}</td><td class="align-right">
				{{ getLabelForDefault }}
			</td>
		</tr>
		<tr>
			<td>{{ t('tables', 'Options') }}</td><td class="align-right">
				{{ getOptionsCount }}
			</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'SelectionTableDisplay',
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	computed: {
		getLabelForDefault() {
			const i = this.getSelectionOptions?.findIndex((obj) => obj.id === this.getDefaultValue)
			return this.getSelectionOptions[i]?.label || null
		},
		getDefaultValue() {
			return parseInt(this.column.selectionDefault)
		},
		getSelectionOptions() {
			return this.column.selectionOptions
		},
		getOptionsCount() {
			return this.getAllNonDeletedOptions.length
		},
		getAllNonDeletedOptions() {
			return this.getSelectionOptions.filter(item => {
				return !item.deleted
			})
		},
	},
}
</script>
<style scoped>

table td {
	padding-right: 10px;
}

</style>
