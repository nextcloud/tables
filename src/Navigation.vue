<template>
	<NcAppNavigation>
		<template #list>
			<div v-if="tablesLoading" class="icon-loading" />
			<ul v-if="!tablesLoading">
				<NcAppNavigationItem :title="t('tables', 'Start page')"
					icon="icon-home"
					@click="$router.push('/').catch(() => {}); closeNav()" />

				<NcAppNavigationCaption :title="t('tables', 'My tables')">
					<template #actions>
						<NcActionButton icon="icon-add" @click="showModalCreateTable = true" />
					</template>
				</NcAppNavigationCaption>
				<CreateTable :show-modal="showModalCreateTable" @close="actionCloseModalNewTable" />

				<NavigationTableItem v-for="table in getOwnTables"
					:key="table.id"
					:table="table" />

				<NcAppNavigationCaption v-if="getSharedTables.length > 0"
					:title="t('tables', 'Shared tables')" />

				<NavigationTableItem v-for="table in getSharedTables"
					:key="table.id"
					:table="table" />
			</ul>
		</template>
		<template #footer>
			<NcAppNavigationSettings :title="t('tables', 'Information')">
				<NcAppNavigationItem :title="t('tables', 'Documentation')"
					icon="icon-info"
					@click="openLink('https://github.com/datenangebot/tables/wiki')" />
				<NcAppNavigationItem :title="t('tables', 'Donations')"
					icon="icon-category-workflow"
					@click="openLink('https://github.com/datenangebot/tables/wiki/Donations')" />
			</NcAppNavigationSettings>
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationSettings, NcAppNavigationItem, NcAppNavigationCaption, NcActionButton } from '@nextcloud/vue'
import CreateTable from './modals/CreateTable.vue'
import NavigationTableItem from './NavigationTableItem.vue'
import { mapState, mapGetters } from 'vuex'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'Navigation',
	components: {
		NavigationTableItem,
		NcAppNavigation,
		CreateTable,
		NcAppNavigationSettings,
		NcAppNavigationItem,
		NcAppNavigationCaption,
		NcActionButton,
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
			return this.tables.filter((item) => { return item.isShared === true }).sort((a, b) => a.title.localeCompare(b.title))
		},
		getOwnTables() {
			return this.tables.filter((item) => { return item.isShared === false }).sort((a, b) => a.title.localeCompare(b.title))
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
		actionCloseModalNewTable() {
			this.showModalCreateTable = false
		},
	},
}
</script>
