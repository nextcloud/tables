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
			const i = this.column.selectionOptions?.findIndex((obj) => obj.id === this.getDefaultValue) || null
			return this.getSelectionOptions[i]?.label || null
		},
		getDefaultValue() {
			return parseInt(this.column.selectionDefault) || null
		},
		getSelectionOptions() {
			return this.column.selectionOptions
		},
		getOptionsCount() {
			return this.getSelectionOptions.length
		},
	},
}
</script>
<style scoped>

table td {
	padding-right: 10px;
}

</style>
