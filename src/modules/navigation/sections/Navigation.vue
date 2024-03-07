<template>
	<NcAppNavigation>
		<template #list>
			<div class="filter-box">
				<NcTextField :value.sync="filterString" :label="t('tables', 'Filter items')"
					trailing-button-icon="close" :show-trailing-button="filterString !== ''"
					@trailing-button-click="filterString = ''">
					<Magnify :size="16" />
				</NcTextField>
			</div>

			<div v-if="tablesLoading" class="icon-loading" />

			<ul v-if="!tablesLoading">
				<NcAppNavigationCaption v-if="getFavoriteNodes.length > 0" :name="t('tables', 'Favorites')" />

				<!-- FAVORITES -->
				<template v-for="node in getFavoriteNodes">
					<NavigationTableItem v-if="!node.tableId" :key="node.id" :filter-string="filterString"
						:table="node" />

					<NavigationViewItem v-else :key="'view' + node.id" :view="node" />
				</template>

				<NcAppNavigationCaption :name="t('tables', 'Tables')">
					<template #actions>
						<NcActionButton :aria-label="t('tables', 'Create table')" icon="icon-add"
							@click.prevent="createTable" />
					</template>
				</NcAppNavigationCaption>

				<!-- ALL NON-FAVORITES -->

				<template v-for="node in getAllNodes">
					<NavigationTableItem v-if="!node.tableId && !node.archived && !node.favorite" :key="node.id"
						:filter-string="filterString" :table="node" />

					<NavigationViewItem v-else-if="node.tableId && !node.favorite && !viewAlreadyListed(node)"
						:key="'view' + node.id" :view="node" />
				</template>

				<!-- ARCHIVED -->
				<NcAppNavigationItem v-if="getArchivedTables.length > 0" :name="t('tables', 'Archived tables')"
					:allow-collapse="true" :open="false">
					<template #icon>
						<Archive :size="20" />
					</template>

					<template #counter>
						<NcCounterBubble>
							{{ getArchivedTables.length }}
						</NcCounterBubble>
					</template>

					<NavigationTableItem v-for="table in getArchivedTables" :key="table.id"
						:filter-string="filterString" :table="table" />
				</NcAppNavigationItem>
			</ul>
			<div v-if="contextsLoading" class="icon-loading" />
			<ul v-if="!contextsLoading">
				<NcAppNavigationCaption :name="t('tables', 'Applications')">
					<template #actions>
						<NcActionButton :aria-label="t('tables', 'Create application')" icon="icon-add"
							@click.prevent="createContext" />
					</template>
				</NcAppNavigationCaption>

				<template v-for="node in getAllContexts">
					<NavigationContextItem :key="node.id" :context="node" />
				</template>
			</ul>
		</template>

		<!-- TODO make filter work for Contexts -->
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
	</NcAppNavigation>
</template>

<script>
import {
	NcAppNavigation,
	NcAppNavigationItem,
	NcAppNavigationCaption,
	NcActionButton,
	NcTextField,
	NcButton,
	NcEmptyContent,
	NcCounterBubble,
} from '@nextcloud/vue'

import NavigationViewItem from '../partials/NavigationViewItem.vue'
import NavigationTableItem from '../partials/NavigationTableItem.vue'
import NavigationContextItem from '../partials/NavigationContextItem.vue'
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
		NavigationContextItem,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppNavigationCaption,
		NcActionButton,
		NcTextField,
		Magnify,
		Archive,
		NcButton,
		NcCounterBubble,
		NcEmptyContent,
	},
	data() {
		return {
			loading: true,
			filterString: '',
		}
	},
	computed: {
		...mapState(['tables', 'views', 'tablesLoading', 'contexts', 'contextsLoading']),
		getAllNodes() {
			return [...this.getFilteredTables, ...this.getOwnViews, ...this.getSharedViews]
		},
		getOwnViews() {
			const sharedTableIds = this.getFilteredTables.map(table => table.id)

			return this.views.filter(view => {
				return !view.isShared && view.ownership === getCurrentUser().uid && sharedTableIds.includes(view.tableId)
			}).filter(view => view.title.toLowerCase().includes(this.filterString.toLowerCase()))
		},
		getSharedViews() {
			const sharedTableIds = this.getFilteredTables.map(table => table.id)

			return this.views.filter(view => {
				return view.isShared && view.ownership !== getCurrentUser().uid && !sharedTableIds.includes(view.tableId)
			}).filter(view => view.title.toLowerCase().includes(this.filterString.toLowerCase()))
		},
		getFavoriteNodes() {
			return this.getAllNodes.filter(node => node.favorite)
		},
		getArchivedTables() {
			return this.getFilteredTables.filter(node => node.archived)
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
		getAllContexts() {
			return this.contexts.filter(context => context.name.toLowerCase().includes(this.filterString.toLowerCase()))
		},
	},
	methods: {
		createTable() {
			emit('tables:table:create')
		},
		createContext() {
			emit('tables:context:create')
		},
		closeNav() {
			if (window.innerWidth < 960) {
				emit('toggle-navigation', {
					open: false,
				})
			}
		},
		viewAlreadyListed(view) {
			return this.getFilteredTables.map(t => t.id).includes(view.tableId)
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