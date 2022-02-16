<template>
	<table v-if="column" style="width: 100%;">
		<tr>
			<td>{{ t('tables', 'Order weight') }}</td><td class="align-right">
				{{ column.orderWeight }}
			</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'TextLinkTableDisplay',
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
