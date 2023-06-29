<template>
	<NcAppNavigationItem v-if="table"
		:name="table.title"
		:class="{active: activeTable && table.id === activeTable.id}"
		:allow-collapse="hasViews"
		:open="false"
		:force-menu="true"
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
			<NcCounterBubble>
				{{ n('tables', '%n row', '%n rows', table.rowsCount, {}) }}
			</NcCounterBubble>
			<NcActionButton v-if="table.hasShares" icon="icon-share" :class="{'margin-right': !(activeTable && table.id === activeTable.id)}" @click="actionShowShare" />
			<div v-if="table.isShared && table.ownership !== userId" class="margin-left">
				<NcAvatar :user="table.ownership" />
			</div>
		</template>

		<template #actions>
			<NcActionButton v-if="canManageElement(table)"
				icon="icon-rename"
				:close-after-click="true"
				@click="editTable">
				{{ t('tables', 'Edit table') }}
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
			<NcActionButton v-if="canDeleteElement(table)"
				icon="icon-delete"
				:close-after-click="true"
				@click="showDeletionConfirmation = true">
				{{ t('tables', 'Delete table') }}
			</NcActionButton>
		</template>
		<NavigationViewItem v-for="view in getViews"
			:key="'view'+view.id"
			:view="view" />
		<DialogConfirmation :description="getTranslatedDescription"
			:title="t('tables', 'Confirm table deletion')"
			:cancel-title="t('tables', 'Cancel')"
			:confirm-title="t('tables', 'Delete')"
			confirm-class="error"
			:show-modal="showDeletionConfirmation"
			@confirm="deleteMe"
			@cancel="showDeletionConfirmation = false" />
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

export default {
	name: 'NavigationTableItem',

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
	},

	data() {
		return {
			showDeletionConfirmation: false,
		}
	},

	computed: {
		...mapGetters(['activeTable']),
		...mapState(['views']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the table "{table}"?', { table: this.table.title })
		},
		userId() {
			return getCurrentUser().uid
		},
		getViews() {
			// TODO: this.table.views exists, but the views are also stored in store views. When changed there the change does not appear in the views attribute of this.table. Use it like done here instead? -> this.table.views unnecessary?:
			return this.views.filter(v => v.tableId === this.table.id)
		},
		hasViews() {
			return this.getViews.length > 0
		},
	},

	methods: {
		async actionShowShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
			await this.$router.push('/table/' + parseInt(this.table.id)).catch(err => err)
		},
		async actionShowImport(table) {
			emit('tables:modal:import', table)
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
		async deleteMe() {
			const deleteId = this.table.id
			const activeTableId = this.activeTable?.id

			const res = await this.$store.dispatch('removeTable', { tableId: this.table.id })
			if (res) {
				showSuccess(t('tables', 'Table "{emoji}{table}" removed.', { emoji: this.table.emoji ? this.table.emoji + ' ' : '', table: this.table.title }))

				// if the actual table was deleted, go to startpage
				if (deleteId === activeTableId) {
					await this.$router.push('/').catch(err => err)
				}
				this.showDeletionConfirmation = false
			}
		},
		editTable() {
			emit('edit-table', this.table)
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
