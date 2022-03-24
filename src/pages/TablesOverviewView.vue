<template>
	<div>
		<div class="row padding-left">
			<div class="col-4 row-with-margin">
				<h2>{{ t('tables', 'All tables') }}</h2>
			</div>
			<div class="col-4">
				<TableBox v-for="table in tables"
					:key="table.id"
					:header="table.title"
					:table-id="table.id">
					{{ t('tables', 'Owner: {ownerName}', { ownerName: table.ownership }) }}
				</TableBox>
			</div>
		</div>
		<EmptyContent v-if="!tables || tables.length === 0" icon="icon-category-organization">
			{{ t('tables', 'No tables') }}
			<template #desc>
				{{ t('tables', 'Please create a table on the left.') }}
			</template>
		</EmptyContent>
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import TableBox from '../partials/TableBox'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

export default {
	name: 'TablesOverviewView',
	components: {
		TableBox,
		EmptyContent,
	},
	data() {
		return {
		}
	},
	computed: {
		...mapState(['tables']),
		...mapGetters(['activeTable']),
	},
}
</script>
