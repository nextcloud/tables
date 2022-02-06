<template>
	<table v-if="column">
		<tr>
			<td>{{ t('tables', 'Order weight') }}</td><td>{{ column.orderWeight }}</td>
		</tr>
		<tr>
			<td>{{ t('tables', 'Decimals') }}</td><td>{{ column.numberDecimals }}</td>
		</tr>

		<tr>
			<td>{{ t('tables', 'Default') }}</td><td>{{ column.numberDefault }}</td>
		</tr>
		<tr>
			<td>{{ t('tables', 'Min') }}</td><td>{{ column.numberMin }}</td>
		</tr>
		<tr>
			<td>{{ t('tables', 'Max') }}</td><td>{{ column.numberMax }}</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'NumberTableDisplay',
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
}
</script>
<style scoped>

table td {
	padding-right: 10px;
}

</style>
