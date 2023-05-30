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

				<NavigationTableItem v-for="table in getOwnTables"
					:key="table.id"
					:table="table"
					@edit-table="id => editTableId = id" />

				<NcAppNavigationCaption v-if="getSharedTables.length > 0"
					:title="t('tables', 'Shared tables')" />

				<NavigationTableItem v-for="table in getSharedTables"
					:key="table.id"
					:table="table"
					@edit-table="id => editTableId = id" />
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
			<EditTable :show-modal="editTableId !== null" :table-id="editTableId" @close="editTableId = null " />
			<Import :show-modal="importTable !== null" :table="importTable" @close="importTable = null" />
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationCaption, NcActionButton, NcTextField, NcButton, NcEmptyContent } from '@nextcloud/vue'
import CreateTable from '../modals/CreateTable.vue'
import EditTable from '../modals/EditTable.vue'
import NavigationTableItem from '../partials/NavigationTableItem.vue'
import { mapState, mapGetters } from 'vuex'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import { getCurrentUser } from '@nextcloud/auth'
import Import from '../modals/Import.vue'

export default {
	name: 'Navigation',
	components: {
		Import,
		NavigationTableItem,
		NcAppNavigation,
		CreateTable,
		EditTable,
		NcAppNavigationCaption,
		NcActionButton,
		NcTextField,
		Magnify,
		NcButton,
		NcEmptyContent,
	},
	data() {
		return {
			loading: true,
			showModalCreateTable: false,
			importTable: null,
			editTableId: null, // if null, no modal open
			filterString: '',
		}
	},
	computed: {
		...mapState(['tables', 'tablesLoading']),
		...mapGetters(['activeTable']),
		getSharedTables() {
			return this.getFilteredTables.filter((item) => { return item.isShared === true && item.ownership !== getCurrentUser().uid }).sort((a, b) => a.title.localeCompare(b.title))
		},
		getOwnTables() {
			return this.getFilteredTables.filter((item) => { return item.isShared === false || item.ownership === getCurrentUser().uid }).sort((a, b) => a.title.localeCompare(b.title))
		},
		getFilteredTables() {
			return this.tables.filter(table => { return table.title.toLowerCase().includes(this.filterString.toLowerCase()) })
		},
	},
	mounted() {
		subscribe('create-table', this.createTable)
		subscribe('edit-table', tableId => { this.editTableId = tableId })
		subscribe('tables:modal:import', table => { this.importTable = table })
	},
	beforeDestroy() {
		unsubscribe('create-table', this.createTable)
		unsubscribe('edit-table', tableId => { this.editTableId = tableId })
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
