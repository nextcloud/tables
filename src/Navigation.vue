<template>
	<AppNavigation>
		<template #list>
			<CreateTable />
			<div v-if="tablesLoading" class="icon-loading" />
			<ul v-if="!tablesLoading">
				<AppNavigationItem
					:title="t('tables', 'Start page')"
					icon="icon-home"
					@click="$router.push('/').catch(() => {})" />

				<NavigationTableItem
					v-for="table in tables"
					:key="table.id"
					:table="table" />
			</ul>
		</template>
		<template #footer>
			<AppNavigationSettings :title="t('tables', 'Information')">
				<AppNavigationItem
					:title="t('tables', 'Documentation')"
					icon="icon-info"
					@click="openLink('https://github.com/datenangebot/tables/wiki')" />
				<AppNavigationItem
					:title="t('tables', 'Donations')"
					icon="icon-category-workflow"
					@click="openLink('https://github.com/datenangebot/tables/wiki/Donations')" />
			</AppNavigationSettings>
		</template>
	</AppNavigation>
</template>

<script>
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import CreateTable from './modals/CreateTable'
import NavigationTableItem from './NavigationTableItem'
import { mapState, mapGetters } from 'vuex'
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'

export default {
	name: 'Navigation',
	components: {
		NavigationTableItem,
		AppNavigation,
		CreateTable,
		AppNavigationSettings,
		AppNavigationItem,
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
		openLink(link) {
			window.open(link, '_blank')
		},
	},
}
</script>
