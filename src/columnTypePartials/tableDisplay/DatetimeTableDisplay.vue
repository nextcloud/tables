<template>
	<table v-if="column" style="width: 100%;">
		<tr>
			<td>{{ t('tables', 'Order weight') }}</td><td class="align-right">
				{{ column.orderWeight }}
			</td>
		</tr>

		<tr>
			<td>{{ t('tables', 'Now as default') }}</td><td class="align-right">
				{{ localValue }}
			</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'DatetimeTableDisplay',
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	computed: {
		localValue() {
			return this.column.datetimeDefault === 'now' ? t('tables', 'Yes') : t('tables', 'No')
		},
	},
}
</script>
<style scoped>

table td {
	padding-right: 10px;
}

</style>
