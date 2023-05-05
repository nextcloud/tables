<template>
	<table v-if="column" style="width: 100%;">
		<tr>
			<td>{{ t('tables', 'Default') }}</td>
			<td v-if="column.textDefault">
				{{ t('tables', 'Default text is set') }}
			</td>
			<td v-if="!column.textDefault">
				{{ t('tables', 'No default text') }}
			</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'TextRichTableDisplay',

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
