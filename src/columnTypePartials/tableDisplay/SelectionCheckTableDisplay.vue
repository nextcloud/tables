<template>
	<table v-if="column" style="width: 100%;">
		<tr>
			<td>{{ t('tables', 'Order weight') }}</td><td class="align-right">
				{{ column.orderWeight }}
			</td>
		</tr>

		<tr>
			<td>{{ t('tables', 'Default') }}</td><td class="align-right">
				{{ localValue }}
			</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'SelectionCheckTableDisplay',
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	computed: {
		localValue() {
			return this.column.selectionDefault === 'true' ? '✓' : '×'
		},
	},
}
</script>
<style scoped>

table td {
	padding-right: 10px;
}

</style>
