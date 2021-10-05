<template>
	<div id="content">
		<Navigation />
		<AppContent>
			<TableDefaultView />
		</AppContent>
	</div>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Navigation from './Navigation'
import '@nextcloud/dialogs/styles/toast.scss'
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
	beforeMount() {
		this.$store.dispatch('loadTablesFromBE')
	},
	methods: {
	},
}
</script>
