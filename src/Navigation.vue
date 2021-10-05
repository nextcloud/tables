<template>
	<AppNavigation>
		<CreateTable />
		<div v-if="tablesLoading" class="icon-loading" />
		<ul>
			<NavigationTableItem v-for="table in tables"
				:key="table.id"
				:table="table" />
		</ul>
	</AppNavigation>
</template>

<script>
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import CreateTable from './modals/CreateTable'
import NavigationTableItem from './NavigationTableItem'
import { mapState, mapGetters } from 'vuex'

export default {
	name: 'Navigation',
	components: {
		NavigationTableItem,
		AppNavigation,
		CreateTable,
	},
	data() {
		return {
			loading: true,
			showModalAddNewTable: false,
		}
	},
	computed: {
		...mapState(['tables', 'tablesLoading']),
		...mapGetters(['activeTable']),
	},
	methods: {
	},
}
</script>
