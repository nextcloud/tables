<template>
	<table v-if="column">
		<tr>
			<td>{{ t('tables', 'Order weight') }}</td><td>{{ column.orderWeight }}</td>
		</tr>
		<tr>
			<td>{{ t('tables', 'Decimals') }}</td><td>{{ column.numberDecimals }}</td>
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
