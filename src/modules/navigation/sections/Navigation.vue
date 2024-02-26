<template>
	<NcAppNavigation>
		<template #list>
			<div class="filter-box">
				<NcTextField :value.sync="filterString"
					:label="t('tables', 'Filter tables')"
					trailing-button-icon="close"
					:show-trailing-button="filterString !== ''"
					@trailing-button-click="filterString = ''">
					<Magnify :size="16" />
				</NcTextField>
			</div>

			<div v-if="tablesLoading" class="icon-loading" />

			<ul v-if="!tablesLoading">
				<NcAppNavigationCaption :name="t('tables', 'My tables')">
					<template #actions>
						<NcActionButton :aria-label="t('tables', 'Create table')" icon="icon-add" @click.prevent="createTable" />
					</template>
				</NcAppNavigationCaption>
				<NavigationTableItem v-for="table in getOwnTables"
					:key="table.id"
					:filter-string="filterString"
					:table="table" />

				<NcAppNavigationCaption v-if="getSharedTables.length > 0 || getSharedViews.length > 0"
					:name="t('tables', 'Shared')" />
				<NavigationTableItem v-for="table in getSharedTables"
					:key="table.id"
					:filter-string="filterString"
					:table="table" />
				<NavigationViewItem v-for="view in getSharedViews"
					:key="'view'+view.id"
					:view="view" />
			</ul>

			<NcAppNavigationItem :name="t('tables', 'Archived tables')" :allow-collapse="true" :open="false">
				<template #icon>
					<Archive :size="24" />
				</template>

				<template #counter>
					0
				</template>
			</NcAppNavigationItem>

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
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationItem, NcAppNavigationCaption, NcActionButton, NcTextField, NcButton, NcEmptyContent } from '@nextcloud/vue'
import NavigationViewItem from '../partials/NavigationViewItem.vue'
import NavigationTableItem from '../partials/NavigationTableItem.vue'
import { mapState } from 'vuex'
import { emit } from '@nextcloud/event-bus'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Archive from 'vue-material-design-icons/Archive.vue'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'Navigation',
	components: {
		NavigationTableItem,
		NavigationViewItem,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationCaption,
		NcActionButton,
		NcTextField,
		Magnify,
		Archive,
		NcButton,
		NcEmptyContent,
	},
	data() {
		return {
			loading: true,
			filterString: '',
		}
	},
	computed: {
		...mapState(['tables', 'views', 'tablesLoading']),
		getSharedViews() {
			const sharedTableIds = this.getFilteredTables.map(table => table.id)
			return this.views.filter(item => item.isShared === true && item.ownership !== getCurrentUser().uid && !sharedTableIds.includes(item.tableId)).filter(view => view.title.toLowerCase().includes(this.filterString.toLowerCase())).sort((a, b) => a.tableId === b.tableId ? a.id - b.id : a.tableId - b.tableId)
		},
		getSharedTables() {
			return this.getFilteredTables.filter((item) => { return item.isShared === true && item.ownership !== getCurrentUser().uid }).sort((a, b) => a.title.localeCompare(b.title))
		},
		getOwnTables() {
			return this.getFilteredTables.filter((item) => { return item.isShared === false || item.ownership === getCurrentUser().uid }).sort((a, b) => a.title.localeCompare(b.title))
		},
		getFilteredTables() {
			return this.tables.filter(table => {
				if (this.filterString === '') {
					return true
				} else {
					return table.title.toLowerCase().includes(this.filterString.toLowerCase())
						|| table.views?.some(view => view.title.toLowerCase().includes(this.filterString.toLowerCase()))
				}
			})
		},
	},
	methods: {
		createTable() {
			emit('tables:table:create')
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
