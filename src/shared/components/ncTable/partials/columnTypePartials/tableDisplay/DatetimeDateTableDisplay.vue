<template>
	<table v-if="column" style="width: 100%;">
		<tr>
			<td>{{ t('tables', 'Today as default') }}</td><td class="align-right">
				{{ localValue }}
			</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'DatetimeDateTableDisplay',
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	computed: {
		localValue() {
			return this.column.datetimeDefault === 'today' ? t('tables', 'Yes') : t('tables', 'No')
		},
	},
}
</script>
<style scoped>

table td {
	padding-right: 10px;
}

</style>
