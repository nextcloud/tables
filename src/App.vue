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
import TableDefaultView from './pages/TableDefaultView'

export default {
	name: 'App',
	components: {
		TableDefaultView,
		AppContent,
		Navigation,
	},
	props: {
		tableId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			activeTable: null,
		}
	},
	mounted() {
		// console.debug('startup route id', this.$router.params)
	},
	async created() {
		await this.$store.dispatch('loadTablesFromBE')

		/*
		if (this.$router && this.$router.params && this.$router.params.tableId && false) {
			console.debug('try to fetch routing from url', this.$router)
			await this.$router.push({
				name: 'table',
				params: { tableId: this.$router.params.tableId },
			})
		} else {
			console.debug('no routing', this.$router)
		}
*/

		this.$watch(
			() => this.$route.params,
			(toParams, previousParams) => {
				// react to route changes...
				// console.debug('change route to', toParams)
				this.$store.commit('setActiveTableId', parseInt(toParams.tableId))
			}
		)
	},
	methods: {
	},
}
</script>
