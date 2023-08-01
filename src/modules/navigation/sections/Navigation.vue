<template>
	<NcAppNavigation>
		<template #list>
			<div v-if="tablesLoading" class="icon-loading" />

			<div class="filter-box">
				<NcTextField :value.sync="filterString"
					:label="t('tables', 'Filter tables')"
					trailing-button-icon="close"
					:show-trailing-button="filterString !== ''"
					@trailing-button-click="filterString = ''">
					<Magnify :size="16" />
				</NcTextField>
			</div>

			<ul v-if="!tablesLoading">
				<NcAppNavigationCaption v-if="getOwnTables.length > 0" :title="t('tables', 'My tables')">
					<template #actions>
						<NcActionButton :aria-label="t('tables', 'Create table')" icon="icon-add" @click.prevent="createTable" />
					</template>
				</NcAppNavigationCaption>
				<NavigationDashboardItem v-for="table in getOwnTables"
					:key="table.id"
					:filter-string="filterString"
					:table="table" />

				<NcAppNavigationCaption v-if="getSharedTables.length > 0"
					:title="t('tables', 'Shared tables')" />

				<NavigationDashboardItem v-for="table in getSharedTables"
					:key="table.id"
					:filter-string="filterString"
					:table="table" />

				<!-- <NcAppNavigationCaption v-if="getSharedViews.length > 0"
					:title="t('tables', 'Shared views')" />

				<NavigationViewItem
					v-for="view in getSharedViews"
					:key="'view'+view.id"
					:view="view" /> -->
			</ul>

			<div v-if="filterString !== ''" class="search-info">
				<NcEmptyContent :description="t('tables', 'Your results are filtered.')">
					<template #icon>
						<Magnify :size="10" />
					</template>
					<template #action>
						<NcButton :aria-label="t('tables', 'Clear filter')" @click="filterString = ''">
							{{ t('tables', 'Clear filter') }}
						</NcButton>
					</template>
				</NcEmptyContent>
			</div>

			<CreateTable :show-modal="showModalCreateTable" @close="showModalCreateTable = false" />
			<Import :show-modal="importToView !== null" :view="importToView" @close="importToView = null" />
			<ViewSettings :view="{tableId: createViewTableId, sort: [], filter: []}" :create-view="true" :show-modal="createViewTableId !== null" @close="createViewTableId = null" />
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationCaption, NcActionButton, NcTextField, NcButton, NcEmptyContent } from '@nextcloud/vue'
import CreateTable from '../modals/CreateTable.vue'
import ViewSettings from '../../main/modals/ViewSettings.vue'
import NavigationViewItem from '../partials/NavigationViewItem.vue'
import NavigationDashboardItem from '../partials/NavigationDashboardItem.vue'
import { mapState } from 'vuex'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import { getCurrentUser } from '@nextcloud/auth'
import Import from '../modals/Import.vue'

export default {
	name: 'Navigation',
	components: {
		Import,
		NavigationDashboardItem,
		NavigationViewItem,
		NcAppNavigation,
		CreateTable,
		NcAppNavigationCaption,
		NcActionButton,
		NcTextField,
		Magnify,
		NcButton,
		NcEmptyContent,
		ViewSettings,
	},
	data() {
		return {
			loading: true,
			showModalCreateTable: false,
			importToView: null,
			createViewTableId: null, // if null, no modal open
			filterString: '',
		}
	},
	computed: {
		...mapState(['tables', 'views', 'tablesLoading']),
		// getSharedViews() {
		// 	const sharedTableIds = this.getFilteredTables.map(table => table.id)
		// 	const sharedBaseViewTableIds = this.views.filter(item => item.isShared === true && item.isBaseView).map(view => view.tableId)
		// 	return this.views.filter(item => item.isShared === true && item.ownership !== getCurrentUser().uid && !sharedTableIds.includes(item.tableId)).filter(view => view.isBaseView || !sharedBaseViewTableIds.includes(view.tableId)).filter(view => view.title.toLowerCase().includes(this.filterString.toLowerCase())).sort((a, b) => a.tableId === b.tableId ? a.id - b.id : a.tableId - b.tableId)
		// },
		getSharedTables() {
			return this.getFilteredTables.filter((item) => { return item.isShared === true && item.ownership !== getCurrentUser().uid }).sort((a, b) => a.title.localeCompare(b.title))
		},
		getOwnTables() {
			return this.getFilteredTables.filter((item) => { return item.isShared === false || item.ownership === getCurrentUser().uid }).sort((a, b) => a.title.localeCompare(b.title))
		},
		getFilteredTables() {
			return this.tables.filter(table => (!this.filterString
				? true
				: (table.title.toLowerCase().includes(this.filterString.toLowerCase()) || table.views.some(view => view.title.toLowerCase().includes(this.filterString.toLowerCase())))))
		},
	},
	mounted() {
		subscribe('create-view', tableId => { this.createViewTableId = tableId })
		subscribe('create-table', this.createTable)
		subscribe('tables:modal:import', table => { this.importToView = table })
	},
	beforeDestroy() {
		unsubscribe('create-view', tableId => { this.createViewTableId = tableId })
		unsubscribe('create-table', this.createTable)
		unsubscribe('tables:modal:import', table => { this.importToView = table })
	},
	methods: {
		createTable() {
			this.showModalCreateTable = true
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

<style lang="scss" scoped>

:deep(.filter-box) {
	.input-field {
		padding: 8px;
	}

	input.input-field__input {
		background-color: var(--color-primary-element-light);
	}
}

.search-info {
	text-align: center;
	justify-content: center;

	.empty-content {
		margin-top: 3vh;
	}
}

</style>
