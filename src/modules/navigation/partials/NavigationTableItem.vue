<template>
	<NcAppNavigationItem v-if="table"
		data-cy="navigationTableItem"
		:name="table.title"
		:class="{active: activeTable && table.id === activeTable.id}"
		:allow-collapse="hasViews"
		:force-menu="true"
		:open.sync="isParentOfActiveView"
		:to="'/table/' + parseInt(table.id)"
		@click="openTable">
		<template #icon>
			<template v-if="table.emoji">
				{{ table.emoji }}
			</template>

			<template v-else>
				<Table :size="20" />
			</template>
		</template>

		<template #counter>
			<NcCounterBubble v-if="canReadData(table)">
				{{ n('tables', '%n row', '%n rows', table.rowsCount, {}) }}
			</NcCounterBubble>
			<NcActionButton v-if="table.hasShares" icon="icon-share" :class="{'margin-right': !(activeTable && table.id === activeTable.id)}" @click="actionShowShare" />
			<div v-if="table.isShared && table.ownership !== userId" class="margin-left">
				<NcAvatar :user="table.ownership" :show-user-status="false" />
			</div>
		</template>

		<template #actions>
			<!-- EDIT -->
			<NcActionButton v-if="canManageElement(table) "
				:close-after-click="true"
				@click="emit('tables:table:edit', table.id)">
				<template #icon>
					<IconRename :size="20" decorative />
				</template>
				{{ t('tables', 'Edit table') }}
			</NcActionButton>

			<!-- CREATE VIEW -->
			<NcActionButton v-if="canManageElement(table)"
				:close-after-click="true"
				@click="createView">
				<template #icon>
					<PlaylistPlus :size="20" />
				</template>
				{{ t('tables', 'Create view') }}
			</NcActionButton>

			<!-- SHARE -->
			<NcActionButton v-if="canShareElement(table)"
				icon="icon-share"
				:close-after-click="true"
				@click="actionShowShare">
				{{ t('tables', 'Share') }}
			</NcActionButton>

			<!-- IMPORT -->
			<NcActionButton v-if="canCreateRowInElement(table)"
				:close-after-click="true"
				@click="actionShowImport(table)">
				{{ t('tables', 'Import') }}
				<template #icon>
					<Import :size="20" />
				</template>
			</NcActionButton>

			<!-- INTEGRATION -->
			<NcActionButton
				:close-after-click="true"
				@click="actionShowIntegration">
				{{ t('tables', 'Integration') }}
				<template #icon>
					<Connection :size="20" />
				</template>
			</NcActionButton>

			<!-- FAVORITE -->
			<NcActionButton v-if="!table.favorite && !table.archived"
				:close-after-click="true"
				@click="toggleFavoriteTable(true)">
				{{ t('tables', 'Add to favorites') }}
				<template #icon>
					<Star :size="20" />
				</template>
			</NcActionButton>

			<!-- UNFAVORITE -->
			<NcActionButton v-if="table.favorite"
				:close-after-click="true"
				@click="toggleFavoriteTable(false)">
				{{ t('tables', 'Remove from favorites') }}
				<template #icon>
					<StarOutline :size="20" />
				</template>
			</NcActionButton>

			<!-- ARCHIVE -->
			<NcActionButton v-if="canManageElement(table) && !table.archived && !table.favorite"
				:close-after-click="true"
				@click="toggleArchiveTable(true)">
				{{ t('tables', 'Archive table') }}
				<template #icon>
					<ArchiveArrowDown :size="20" />
				</template>
			</NcActionButton>

			<!-- UNARCHIVE -->
			<NcActionButton v-if="canManageElement(table) && table.archived"
				:close-after-click="true"
				@click="toggleArchiveTable(false)">
				{{ t('tables', 'Unarchive table') }}
				<template #icon>
					<ArchiveArrowUpOutline :size="20" />
				</template>
			</NcActionButton>

			<!-- DELETE -->
			<NcActionButton v-if="canManageElement(table)"
				icon="icon-delete"
				:close-after-click="true"
				@click="deleteTable()">
				{{ t('tables', 'Delete table') }}
			</NcActionButton>
		</template>
		<NavigationViewItem v-for="view in getViews"
			:key="'view'+view.id"
			:view="view"
			:show-share-sender="false" />
	</NcAppNavigationItem>
</template>

<script>
import { NcActionButton, NcAppNavigationItem, NcCounterBubble, NcAvatar } from '@nextcloud/vue'
import '@nextcloud/dialogs/style.css'
import { mapGetters, mapState } from 'vuex'
import { emit } from '@nextcloud/event-bus'
import Table from 'vue-material-design-icons/Table.vue'
import Star from 'vue-material-design-icons/Star.vue'
import StarOutline from 'vue-material-design-icons/StarOutline.vue'
import ArchiveArrowDown from 'vue-material-design-icons/ArchiveArrowDown.vue'
import ArchiveArrowUpOutline from 'vue-material-design-icons/ArchiveArrowUpOutline.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import { getCurrentUser } from '@nextcloud/auth'
import Connection from 'vue-material-design-icons/Connection.vue'
import Import from 'vue-material-design-icons/Import.vue'
import NavigationViewItem from './NavigationViewItem.vue'
import PlaylistPlus from 'vue-material-design-icons/PlaylistPlus.vue'
import IconRename from 'vue-material-design-icons/Rename.vue'

export default {

	components: {
		IconRename,
		// eslint-disable-next-line vue/no-reserved-component-names
		Table,
		Star,
		StarOutline,
		ArchiveArrowDown,
		ArchiveArrowUpOutline,
		Import,
		NavigationViewItem,
		NcActionButton,
		NcAppNavigationItem,
		NcCounterBubble,
		NcAvatar,
		Connection,
		PlaylistPlus,
	},

	filters: {
		truncate(string, num) {
			if (string.length >= num) {
				return string.substring(0, num) + '...'
			} else {
				return string
			}
		},
	},

	mixins: [permissionsMixin],

	props: {
		table: {
			type: Object,
			default: null,
		},
		filterString: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			isParentOfActiveView: false,
		}
	},

	computed: {
		...mapGetters(['activeTable', 'activeView']),
		...mapState(['views']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the table "{table}"?', { table: this.table.title })
		},
		userId() {
			return getCurrentUser().uid
		},
		getViews() {
			return this.views.filter(v => v.tableId === this.table.id && v.title.toLowerCase().includes(this.filterString.toLowerCase()))
		},
		hasViews() {
			return this.getViews.length > 0
		},
	},
	watch: {
		activeView() {
			if (!this.isParentOfActiveView && this.activeView?.tableId === this.table?.id) {
				this.isParentOfActiveView = true
			}
		},
		filterString() {
			if (!this.isParentOfActiveView && this.filterString && !this.table.title.toLowerCase().includes(this.filterString.toLowerCase())) {
				this.isParentOfActiveView = true
			}
		},
	},
	methods: {
		emit,
		deleteTable() {
			emit('tables:table:delete', this.table)
		},
		createView() {
			emit('tables:view:create', { tableId: this.table.id })
		},
		async actionShowShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
			await this.$router.push('/table/' + parseInt(this.table.id)).catch(err => err)
		},
		async actionShowImport(table) {
			emit('tables:modal:import', { element: table, isView: false })
		},
		async actionShowIntegration() {
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
			await this.$router.push('/table/' + parseInt(this.table.id)).catch(err => err)
		},
		openTable() {
			this.isParentOfActiveView = true
			// Close navigation
			if (window.innerWidth < 960) {
				emit('toggle-navigation', {
					open: false,
				})
			}
		},
		async toggleArchiveTable(archived) {
			await this.$store.dispatch('updateTable', {
				id: this.table.id,
				data: { archived },
			})
		},
		async toggleFavoriteTable(favorite) {
			if (favorite) {
				await this.$store.dispatch('favoriteTable', {
					id: this.table.id,
				})
			} else {
				await this.$store.dispatch('removeFavoriteTable', {
					id: this.table.id,
				})
			}
		},
	},
}
</script>
<style lang="scss">

.app-navigation-entry__counter-wrapper {
	button.action-button {
		padding-right: 0;
	}

	.counter-bubble__counter {
		display: none;
	}
	margin-right: 0 !important;
}

.app-navigation-entry {
	.margin-right {
		margin-right: 44px;
	}
	.margin-left {
		margin-left: calc(var(--default-grid-baseline) * 2);
	}
}

.app-navigation-entry:hover {
	.margin-right {
		margin-right: 0;
	}

	.app-navigation-entry__counter-wrapper .counter-bubble__counter {
		display: inline-flex;
	}
}

</style>
