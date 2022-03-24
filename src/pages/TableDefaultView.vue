<template>
	<div>
		<div v-if="loading" class="icon-loading" />

		<div v-if="!loading && activeTable">
			<div class="row-with-margin">
				<TableDescription :active-table="activeTable"
					:columns="columns"
					@reload="getColumnsForTableFromBE(activeTable.id); getRowsForTableFromBE(activeTable.id)" />
			</div>
			<div class="row">
				<NcTable :rows="rows" :columns="columns" @update-rows="getRowsForTableFromBE(activeTable.id)" />
			</div>
		</div>
	</div>
</template>

<script>
import TableDescription from './sections/TableDescription'
import NcTable from './sections/NcTable'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showWarning } from '@nextcloud/dialogs'
import { mapState, mapGetters } from 'vuex'

export default {
	name: 'TableDefaultView',
	components: {
		TableDescription,
		NcTable,
	},
	data() {
		return {
			loading: false,
			columns: null,
			rows: null,
		}
	},
	computed: {
		...mapState(['tables']),
		...mapGetters(['activeTable']),
	},
	watch: {
		async activeTable() {
			console.debug('table changed, I will try to fetch columns')
			await this.getColumnsForTableFromBE(this.activeTable.id)
			await this.getRowsForTableFromBE(this.activeTable.id)
		},
	},
	methods: {
		async getColumnsForTableFromBE(tableId) {
			this.loading = true
			if (!tableId) {
				this.columns = null
			} else {
				try {
					const res = await axios.get(generateUrl('/apps/tables/column/' + tableId))
					if (res.status !== 200) {
						showWarning(t('tables', 'Sorry, something went wrong.'))
						console.debug('axios error', res)
					}
					if (res.data && Array.isArray(res.data)) {
						this.columns = res.data.sort(this.compareColumns)
					}
				} catch (e) {
					console.error(e)
					showError(t('tables', 'Could not fetch columns for table'))
				}
			}
			this.loading = false
		},
		async getRowsForTableFromBE(tableId) {
			this.loading = true
			if (!tableId) {
				this.rows = null
			} else {
				try {
					const res = await axios.get(generateUrl('/apps/tables/row/' + tableId))
					if (res.status !== 200) {
						showWarning(t('tables', 'Sorry, something went wrong.'))
						console.debug('axios error', res)
					}
					this.rows = res.data
				} catch (e) {
					console.error(e)
					showError(t('tables', 'Could not fetch rows for table'))
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
