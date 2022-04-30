<template>
	<AppNavigation>
		<template #list>
			<div v-if="tablesLoading" class="icon-loading" />
			<ul v-if="!tablesLoading">
				<AppNavigationItem
					:title="t('tables', 'Start page')"
					icon="icon-home"
					@click="$router.push('/').catch(() => {}); closeNav()" />

				<AppNavigationCaption
					:title="t('tables', 'My tables')">
					<template #actions>
						<ActionButton icon="icon-add" @click="showModalCreateTable = true" />
					</template>
				</AppNavigationCaption>
				<CreateTable :show-modal="showModalCreateTable" @close="showModalCreateTable = false" />

				<NavigationTableItem
					v-for="table in getOwnTables"
					:key="table.id"
					:table="table" />

				<AppNavigationCaption
					:title="t('tables', 'Shared tables')" />

				<NavigationTableItem
					v-for="table in getSharedTables"
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
import { emit } from '@nextcloud/event-bus'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'Navigation',
	components: {
		NavigationTableItem,
		AppNavigation,
		CreateTable,
		AppNavigationSettings,
		AppNavigationItem,
		AppNavigationCaption,
		ActionButton,
	},
	data() {
		return {
			loading: true,
			showModalAddNewTable: false,
			showModalCreateTable: false,
		}
	},
	computed: {
		...mapState(['tables', 'tablesLoading']),
		...mapGetters(['activeTable']),
		getSharedTables() {
			return this.tables.filter((item) => { return item.isShared === true })
		},
		getOwnTables() {
			return this.tables.filter((item) => { return item.isShared === false })
		},
	},
	methods: {
		openLink(link) {
			window.open(link, '_blank')
		},
		closeNav() {
			if (window.innerWidth < 960) {
				emit('toggle-navigation', {
					open: false,
				})
			}
		},
	},
}
</script>
