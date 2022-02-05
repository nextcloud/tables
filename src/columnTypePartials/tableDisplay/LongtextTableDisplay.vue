<template>
	<table v-if="column">
		<tr>
			<td>{{ t('tables', 'Order weight') }}</td><td>{{ column.orderWeight }}</td>
		</tr>
		<tr>
			<td>{{ t('tables', 'Default') }}</td><td>{{ column.textDefault | truncate(20, '...') }}</td>
		</tr>
		<tr>
			<td>{{ t('tables', 'Max length') }}</td><td>{{ maxLength }}</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'LongtextTableDisplay',
	filters: {
		truncate(text, length, suffix) {
			if (text.length > length) {
				return text.substring(0, length) + suffix
			} else {
				return text
			}
		},
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	computed: {
		maxLength() {
			return (this.column.textMaxLength && this.column.textMaxLength === -1) ? '' : this.column.textMaxLength
		},
	},
}
</script>
<style scoped>

table td {
	padding-right: 10px;
}

</style>
