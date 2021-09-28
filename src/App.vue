<template>
	<div id="content">
		<Navigation :active-table="activeTable" @updateActiveTable="loadTableFromBE" />
		<AppContent>
			<TableDefaultView :active-table="activeTable" />
		</AppContent>
	</div>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Navigation from './Navigation'
import '@nextcloud/dialogs/styles/toast.scss'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import TableDefaultView from './pages/TableDefaultView'

export default {
	name: 'App',
	components: {
		TableDefaultView,
		AppContent,
		Navigation,
	},
	data() {
		return {
			activeTable: null,
		}
	},
	methods: {
		async loadTableFromBE(tableId) {
			try {
				const response = await axios.get(generateUrl('/apps/tables/table/' + tableId))
				this.activeTable = response.data
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not fetch table'))
			}
		},
	},
}
</script>
