<template>
	<NcContent app-name="tables">
		<Navigation />
		<NcAppContent>
			<div v-if="somethingIsLoading" class="icon-loading" />

			<router-view v-if="!somethingIsLoading" />
		</NcAppContent>
		<Sidebar />
	</NcContent>
</template>

<script>
import { NcContent, NcAppContent } from '@nextcloud/vue'
import Navigation from './modules/navigation/sections/Navigation.vue'
import { mapGetters, mapState } from 'vuex'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import Sidebar from './modules/sidebar/sections/Sidebar.vue'

export default {
	name: 'App',
	components: {
		Sidebar,
		NcContent,
		NcAppContent,
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
<style scoped>

	.sidebar-icon {
		position: absolute;
		right: 5px;
		top: 5px;
	}

</style>
