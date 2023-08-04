<template>
	<NcAppNavigationItem v-if="table"
		:name="table.title"
		:class="{active: activeTable && table.id === activeTable.id}"
		:allow-collapse="hasViews"
		:force-menu="true"
		:open="isParentOfActiveView"
		:to="'/table/' + parseInt(table.id)"
		@click="closeNav">
		<template #icon>
			<template v-if="table.emoji">
				{{ table.emoji }}
			</template>
			<template v-else>
				<Table :size="20" />
			</template>
		</template>
		<template #extra />
		<template #counter>
			<NcCounterBubble v-if="canReadData(table)">
				{{ n('tables', '%n row', '%n rows', table.rowsCount, {}) }}
			</NcCounterBubble>
			<NcActionButton v-if="table.hasShares" icon="icon-share" :class="{'margin-right': !(activeTable && table.id === activeTable.id)}" @click="actionShowShare" />
			<div v-if="table.isShared && table.ownership !== userId" class="margin-left">
				<NcAvatar :user="table.ownership" />
			</div>
		</template>

		<template #actions>
			<NcActionButton v-if="canManageElement(table)"
				:close-after-click="true"
				@click="createView">
				<template #icon>
					<PlaylistPlus :size="20" />
				</template>
				{{ t('tables', 'Create view') }}
			</NcActionButton>
			<NcActionButton v-if="canShareElement(table)"
				icon="icon-share"
				:close-after-click="true"
				@click="actionShowShare">
				{{ t('tables', 'Share') }}
			</NcActionButton>
			<NcActionButton v-if="canCreateRowInElement(table)"
				:close-after-click="true"
				@click="actionShowImport(table)">
				{{ t('tables', 'Import') }}
				<template #icon>
					<Import :size="20" />
				</template>
			</NcActionButton>
			<NcActionButton
				:close-after-click="true"
				@click="actionShowIntegration">
				{{ t('tables', 'Integration') }}
				<template #icon>
					<Creation :size="20" />
				</template>
			</NcActionButton>
			<NcActionButton v-if="canManageElement(table)"
				icon="icon-delete"
				:close-after-click="true"
				@click="deleteTable()">
				{{ t('tables', 'Delete table') }}
			</NcActionButton>
		</template>
		<NavigationViewItem v-for="view in getViews"
			:key="'view'+view.id"
			:view="view" />

	</NcAppNavigationItem>
</template>
<script>
import { NcActionButton, NcAppNavigationItem, NcCounterBubble, NcAvatar } from '@nextcloud/vue'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import DialogConfirmation from '../../../shared/modals/DialogConfirmation.vue'
import { mapGetters, mapState } from 'vuex'
import { emit } from '@nextcloud/event-bus'
import Table from 'vue-material-design-icons/Table.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import { getCurrentUser } from '@nextcloud/auth'
import Creation from 'vue-material-design-icons/Creation.vue'
import Import from 'vue-material-design-icons/Import.vue'
import NavigationViewItem from './NavigationViewItem.vue'
import PlaylistPlus from 'vue-material-design-icons/PlaylistPlus.vue'

export default {
	name: 'NavigationDashboardItem',

	components: {
		// eslint-disable-next-line vue/no-reserved-component-names
		Table,
		Import,
		DialogConfirmation,
		NavigationViewItem,
		NcActionButton,
		NcAppNavigationItem,
		NcCounterBubble,
		NcAvatar,
		Creation,
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
			return this.views.filter(v => v.tableId === this.table.id)
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
		deleteTable() {
			emit('tables:table:delete', this.table)
		},
		createView() {
			emit('tables:view:create', this.table.id)
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
		closeNav(e) {
			if (window.innerWidth < 960) {
				emit('toggle-navigation', {
					open: false,
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
