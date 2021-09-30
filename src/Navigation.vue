<template>
	<AppNavigation>
		<CreateTable @updateTables="loadTablesFromBE" />
		<ul>
			<NavigationTableItem v-for="table in tables"
				:key="table.id"
				:table="table"
				:active-table="activeTable"
				@reloadNecessary="loadTablesFromBE"
				@updateActiveTable="updateActiveTable"
				@activeTableWasDeleted="updateActiveTable(null)" />
		</ul>
	</AppNavigation>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import CreateTable from './modals/CreateTable'
import NavigationTableItem from './NavigationTableItem'

export default {
	name: 'Navigation',
	components: {
		NavigationTableItem,
		AppNavigation,
		CreateTable,
	},
	props: {
		activeTable: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			loading: true,
			tables: [],
			showModalAddNewTable: false,
		}
	},
	async mounted() {
		await this.loadTablesFromBE()
		this.loading = false
	},
	methods: {
		async loadTablesFromBE() {
			try {
				const response = await axios.get(generateUrl('/apps/tables/table'))
				this.tables = response.data
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not fetch tables'))
			}
		},
		async updateActiveTable(tableId) {
			// eslint-disable-next-line vue/custom-event-name-casing
			this.$emit('updateActiveTable', tableId)
		},
	},
}
</script>
