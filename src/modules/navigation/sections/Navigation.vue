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
						<NcActionButton icon="icon-add" @click.prevent="showModalCreateTable = true" />
					</template>
				</NcAppNavigationCaption>

				<NavigationTableItem v-for="table in getOwnTables"
					:key="table.id"
					:table="table"
					@edit-table="id => editTableId = id" />

				<NcAppNavigationCaption v-if="getSharedTables.length > 0"
					:title="t('tables', 'Shared tables')" />

				<NavigationTableItem v-for="table in getSharedTables"
					:key="table.id"
					:table="table" />
			</ul>

			<CreateTable :show-modal="showModalCreateTable" @close="showModalCreateTable = false" />
			<EditTable :show-modal="editTableId !== null" :table-id="editTableId" @close="editTableId = null " />
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationItem, NcAppNavigationCaption, NcActionButton } from '@nextcloud/vue'
import CreateTable from '../modals/CreateTable.vue'
import EditTable from '../modals/EditTable.vue'
import NavigationTableItem from '../partials/NavigationTableItem.vue'
import { mapState, mapGetters } from 'vuex'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'Navigation',
	components: {
		NavigationTableItem,
		NcAppNavigation,
		CreateTable,
		EditTable,
		NcAppNavigationItem,
		NcAppNavigationCaption,
		NcActionButton,
	},
	data() {
		return {
			loading: true,
			showModalCreateTable: false,
			editTableId: null, // if null, no modal open
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
