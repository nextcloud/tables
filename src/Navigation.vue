<template>
	<AppNavigation>
		<CreateTable @updateTables="loadTablesFromBE" />
		<ul>
			<AppNavigationItem v-for="table in tables"
				:key="table.id"
				:title="table.title"
				:class="{active: activeTable && table.id === activeTable.id}"
				icon="icon-category-organization"
				:undo="undo"
				@click="updateActiveTable(table.id)">
				<template slot="actions">
					<ActionButton
						icon="icon-delete"
						@click="deleteTable(table.id)">
						{{ t('tables', 'Delete table') }}
					</ActionButton>
					<ActionButton
						icon="icon-delete"
						@click="undo = true">
						{{ t('tables', 'Undo test') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
		</ul>
	</AppNavigation>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showMessage } from '@nextcloud/dialogs'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import CreateTable from './modals/CreateTable'

export default {
	name: 'Navigation',
	components: {
		ActionButton,
		AppNavigation,
		AppNavigationItem,
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
			undo: false,
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
		updateActiveTable(tableId) {
			console.debug('update selected table', tableId)
			// eslint-disable-next-line vue/custom-event-name-casing
			this.$emit('updateActiveTable', tableId)
		},
		async deleteTable(tableId) {
			this.loading = true
			try {
				const response = await axios.delete(generateUrl('/apps/tables/table/' + tableId))
				console.debug('table deleted', response)
				showMessage(t('tables', 'Table "{table}" deleted.', { table: response.data.title }))
				await this.loadTablesFromBE()
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not fetch tables'))
			}
		},
	},
}
</script>
