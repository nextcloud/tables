<template>
	<div id="content">
		<Navigation />
		<AppContent>
			<div v-if="somethingIsLoading" class="icon-loading" />

			<router-view v-if="!somethingIsLoading" />
		</AppContent>
	</div>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Navigation from './Navigation'
import { mapGetters, mapState } from 'vuex'

export default {
	name: 'App',
	components: {
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
			loading: false,
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
		'$route'(to, from) {
			// console.debug('route changed', { to, from })
			this.$store.commit('setActiveTableId', parseInt(to.params.tableId))
		},
	},
	async created() {
		await this.$store.dispatch('loadTablesFromBE')
		this.$store.commit('setActiveTableId', parseInt(this.$router.currentRoute.params.tableId))
	},
}
</script>
