<template>
	<table v-if="column" style="width: 100%;">
		<tr>
			<td>{{ t('tables', 'Order weight') }}</td><td class="align-right">
				{{ column.orderWeight }}
			</td>
		</tr>

		<tr>
			<td>{{ t('tables', 'Default') }}</td><td class="align-right">
				{{ column.numberDefault }}
			</td>
		</tr>
		<tr>
			<td>{{ t('tables', 'Min') }}</td><td class="align-right">
				{{ column.numberMin }}
			</td>
		</tr>
		<tr>
			<td>{{ t('tables', 'Max') }}</td><td class="align-right">
				{{ column.numberMax }}
			</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'NumberProgressTableDisplay',
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
