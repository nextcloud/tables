<template>
	<div id="content">
		<Navigation />
		<AppContent>
			<div v-if="somethingIsLoading" class="icon-loading" />

			<TablesOverviewView v-if="!somethingIsLoading && !activeTable && tables.length > 0" />

			<EmptyContent v-if="tables.length === 0" icon="icon-category-organization">
				{{ t('tables', 'No tables') }}
				<template #desc>
					{{ t('tables', 'Please create a table on the left.') }}
				</template>
			</EmptyContent>

			<TableDefaultView v-if="!somethingIsLoading && activeTable" />
		</AppContent>
	</div>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Navigation from './Navigation'
import TableDefaultView from './pages/TableDefaultView'
import { mapGetters, mapState } from 'vuex'
import TablesOverviewView from './pages/TablesOverviewView'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

export default {
	name: 'App',
	components: {
		TablesOverviewView,
		TableDefaultView,
		AppContent,
		Navigation,
		EmptyContent,
	},
	props: {
		tableId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			// activeTable: null,
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
