<template>
	<div>
		<div v-if="!activeTable" class="row-with-margin">
			<EmptyContent icon="icon-category-organization">
				{{ t('tables', 'No table in context') }}
				<template #desc>
					{{ t('tables', 'Please create or select a table from the left.') }}
				</template>
			</EmptyContent>
		</div>
		<div v-else>
			<div class="row-with-margin">
				<TableDescription :active-table="activeTable" />
			</div>
			<div class="row">
				<NcTable />
			</div>
		</div>
	</div>
</template>

<script>
import TableDescription from './sections/TableDescription'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import NcTable from './sections/NcTable'

export default {
	name: 'TableDefaultView',
	components: {
		TableDescription,
		EmptyContent,
		NcTable,
	},
	props: {
		activeTable: {
			type: Object,
			default: null,
		},
	},
}
</script>
