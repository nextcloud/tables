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
			<div class="row-with-margin">
				<button @click="showCreateColumn = true">
					Add column
				</button>
				<CreateColumn :show-modal="showCreateColumn" @close="showCreateColumn = false" />
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
import CreateColumn from '../modals/CreateColumn'

export default {
	name: 'TableDefaultView',
	components: {
		CreateColumn,
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
	data() {
		return {
			columns: null,
			showCreateColumn: false,
		}
	},
	watch: {
		activeTable() {
			console.debug('table changed, I will try to fetch columns')
			this.getColumnsForTableFromBE(this.activeTable.id)
		},
	},
	methods: {
		async getColumnsForTableFromBE(tableId) {
			if (!tableId) {
				this.columns = null
			} else {
				try {
					console.debug('try to fetch columns for table id: ', tableId)
					const response = await axios.get(generateUrl('/apps/tables/column/' + tableId))
					this.columns = response.data
					console.debug('columns loaded', this.columns)
				} catch (e) {
					console.error(e)
					showError(t('tables', 'Could not fetch columns for table'))
				}
			}
		},
	},
}
</script>
