<template>
	<div>
		<div v-if="somethingIsLoading" class="icon-loading" />
		<div v-if="!somethingIsLoading && !activeTable" class="row-with-margin">
			<EmptyContent icon="icon-category-organization">
				{{ t('tables', 'No table in context') }}
				<template #desc>
					{{ t('tables', 'Please create or select a table from the left.') }}
				</template>
			</EmptyContent>
		</div>
		<div v-if="!somethingIsLoading && activeTable">
			<div class="row-with-margin">
				<TableDescription :active-table="activeTable" />
			</div>
			<div class="row">
				<NcTable :columns="columns" />
			</div>
		</div>
	</div>
</template>

<script>
import TableDescription from './sections/TableDescription'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import NcTable from './sections/NcTable'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { mapState, mapGetters } from 'vuex'

export default {
	name: 'TableDefaultView',
	components: {
		TableDescription,
		EmptyContent,
		NcTable,
	},
	data() {
		return {
			loading: false,
			columns: null,
		}
	},
	computed: {
		...mapState(['tables', 'tablesLoading']),
		...mapGetters(['activeTable']),
		somethingIsLoading() {
			return this.tablesLoading || this.loading
		},
	},
	watch: {
		activeTable() {
			console.debug('table changed, I will try to fetch columns')
			this.getColumnsForTableFromBE(this.activeTable.id)
		},
	},
	methods: {
		async getColumnsForTableFromBE(tableId) {
			this.loading = true
			if (!tableId) {
				this.columns = null
			} else {
				try {
					console.debug('try to fetch columns for table id: ', tableId)
					const response = await axios.get(generateUrl('/apps/tables/column/' + tableId))
					this.columns = response.data.sort(this.compareColumns)
					console.debug('columns loaded', this.columns)
				} catch (e) {
					console.error(e)
					showError(t('tables', 'Could not fetch columns for table'))
				}
			}
			this.loading = false
		},
		compareColumns(a, b) {
			if (a.orderWeight < b.orderWeight) { return 1 }
			if (a.orderWeight > b.orderWeight) { return -1 }
			return 0
		},
	},
}
</script>
