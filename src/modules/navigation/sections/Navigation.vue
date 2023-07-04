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
				<NcAppNavigationCaption :title="t('tables', 'My tables')">
					<template #actions>
						<NcActionButton :aria-label="t('tables', 'Create table')" icon="icon-add" @click.prevent="createTable" />
					</template>
				</NcAppNavigationCaption>
				<NavigationBaseViewItem v-for="view in getOwnBaseViews"
					:key="view.id"
					:base-view="view" />
				<NcAppNavigationCaption v-if="getSharedBaseViews.length > 0"
					:title="t('tables', 'Shared tables')" />

				<NavigationBaseViewItem v-for="view in getSharedBaseViews"
					:key="view.id"
					:base-view="view" />

				<NcAppNavigationCaption v-if="getSharedViews.length > 0"
					:title="t('tables', 'Shared views')" />

				<NavigationViewItem v-for="view in getSharedViews"
					:key="'view'+view.id"
					:view="view" />
			</ul>

			<div v-if="filterString !== ''" class="search-info">
				<NcEmptyContent :description="t('tables', 'Your results are filtered.')">
					<template #icon>
						<Magnify :size="10" />
					</template>
					<template #action>
						<NcButton @click="filterString = ''">
							{{ t('tables', 'Clear filter') }}
						</NcButton>
					</template>
				</NcEmptyContent>
			</div>

			<CreateTable :show-modal="showModalCreateTable" @close="showModalCreateTable = false" />
			<!-- <EditTable :show-modal="editTable !== null" :table="editTable" @close="editTable = null " /> -->
			<EditViewTitle :show-modal="editView !== null" :view="editView" @close="editView = null " />
			<Import :show-modal="importTable !== null" :table="importTable" @close="importTable = null" />
			<CreateView :show-modal="createView !== null" :table-id="createView?.tableId" @close="createView = null" />
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationCaption, NcActionButton, NcTextField, NcButton, NcEmptyContent } from '@nextcloud/vue'
import CreateTable from '../modals/CreateTable.vue'
// import EditTable from '../modals/EditTable.vue'
import EditViewTitle from '../modals/EditViewTitle.vue'
import CreateView from '../modals/CreateView.vue'
import NavigationViewItem from '../partials/NavigationViewItem.vue'
import NavigationBaseViewItem from '../partials/NavigationBaseViewItem.vue'
import { mapState, mapGetters } from 'vuex'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import { getCurrentUser } from '@nextcloud/auth'
import Import from '../modals/Import.vue'

export default {
	name: 'Navigation',
	components: {
		Import,
		NavigationBaseViewItem,
		NavigationViewItem,
		NcAppNavigation,
		CreateTable,
		// EditTable,
		EditViewTitle,
		NcAppNavigationCaption,
		NcActionButton,
		NcTextField,
		Magnify,
		NcButton,
		NcEmptyContent,
		CreateView,
	},
	data() {
		return {
			loading: true,
			showModalCreateTable: false,
			importTable: null,
			editTable: null, // if null, no modal open
			editView: null, // if null, no modal open
			createView: null, // if null, no modal open
			filterString: '',
		}
	},
	computed: {
		...mapState(['tables', 'views', 'tablesLoading']),
		// ...mapGetters(['activeTable']),
		getSharedBaseViews() {
			return this.getFilteredBaseViews.filter((item) => { return item.isShared === true && item.ownership !== getCurrentUser().uid }).sort((a, b) => a.title.localeCompare(b.title))
		},
		getSharedViews() {
			return this.views.filter((item) => { return item.isShared === true && item.ownership !== getCurrentUser().uid }).sort((a, b) => a.title.localeCompare(b.title))
		},
		getOwnBaseViews() {
			return this.getFilteredBaseViews.filter((item) => { return item.isShared === false || item.ownership === getCurrentUser().uid }).sort((a, b) => a.title.localeCompare(b.title))
		},
		getFilteredBaseViews() {
			return this.tables.map(table => this.views.find(view => view.id === table.baseView.id)).filter(view => { return view.title.toLowerCase().includes(this.filterString.toLowerCase()) })
		},
	},
	mounted() {
		subscribe('create-view', baseView => { this.createView = baseView })
		subscribe('create-table', this.createTable)
		// subscribe('edit-table', table => { this.editTable = table })
		subscribe('edit-view', view => { this.editView = view })
		subscribe('tables:modal:import', table => { this.importTable = table })
	},
	beforeDestroy() {
		unsubscribe('create-view', baseView => { this.createView = baseView })
		unsubscribe('create-table', this.createTable)
		// unsubscribe('edit-table', table => { this.editTable = table })
		unsubscribe('edit-view', view => { this.editView = view })
		unsubscribe('tables:modal:import', table => { this.importTable = table })
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
