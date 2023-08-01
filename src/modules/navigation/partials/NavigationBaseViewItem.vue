<template>
	<NcAppNavigationItem v-if="baseView"
		:name="baseView.title"
		:class="{active: activeView && baseView.id === activeView.id}"
		:allow-collapse="hasViews"
		:force-menu="true"
		:open="isParentOfActiveView"
		:to="'/view/' + parseInt(baseView.id)"
		@click="closeNav">
		<template #icon>
			<template v-if="baseView.emoji">
				{{ baseView.emoji }}
			</template>
			<template v-else>
				<Table :size="20" />
			</template>
		</template>
		<template #extra />
		<template #counter>
			<NcCounterBubble v-if="canReadData(baseView)">
				{{ n('tables', '%n row', '%n rows', baseView.rowsCount, {}) }}
			</NcCounterBubble>
			<NcActionButton v-if="baseView.hasShares" icon="icon-share" :class="{'margin-right': !(activeView && baseView.id === activeView.id)}" @click="actionShowShare" />
			<div v-if="baseView.isShared && baseView.ownership !== userId" class="margin-left">
				<NcAvatar :user="baseView.ownership" />
			</div>
		</template>

		<template #actions>
			<NcActionButton v-if="canManageTable(baseView)"
				:close-after-click="true"
				@click="createView">
				<template #icon>
					<PlaylistPlus :size="20" />
				</template>
				{{ t('tables', 'Create view') }}
			</NcActionButton>
			<NcActionButton v-if="canShareElement(baseView)"
				icon="icon-share"
				:close-after-click="true"
				@click="actionShowShare">
				{{ t('tables', 'Share') }}
			</NcActionButton>
			<NcActionButton v-if="canCreateRowInElement(baseView)"
				:close-after-click="true"
				@click="actionShowImport(baseView)">
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
			<NcActionButton v-if="canManageTable(baseView)"
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
import PlaylistPlus from 'vue-material-design-icons/PlaylistPlus.vue'

export default {
	name: 'NavigationBaseViewItem',

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
		baseView: {
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
			showDeletionConfirmation: false,
			isParentOfActiveView: false,
		}
	},

	computed: {
		...mapGetters(['activeView']),
		...mapState(['views']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the table "{table}"?', { table: this.baseView.title })
		},
		userId() {
			return getCurrentUser().uid
		},
		getViews() {
			return this.views.filter(v => !v.isBaseView && v.tableId === this.baseView.tableId)
		},
		hasViews() {
			return this.getViews.length > 0
		},
	},
	watch: {
		activeView() {
			if (!this.isParentOfActiveView && this.activeView?.id !== this.baseView?.id && this.activeView?.tableId === this.baseView?.tableId) {
				this.isParentOfActiveView = true
			}
		},
		filterString() {
			if (!this.isParentOfActiveView && this.filterString && !this.baseView.title.toLowerCase().includes(this.filterString.toLowerCase())) {
				this.isParentOfActiveView = true
			}
		},
	},
	methods: {
		createView() {
			emit('create-view', this.baseView.tableId)
		},
		async editView() {
			await this.$router.push('/view/' + parseInt(this.baseView.id)).catch(err => err)
			emit('tables:view:edit', this.baseView)
		},
		async actionShowShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
			await this.$router.push('/view/' + parseInt(this.baseView.id)).catch(err => err)
		},
		async actionShowImport(view) {
			emit('tables:modal:import', view)
		},
		async actionShowIntegration() {
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
			await this.$router.push('/view/' + parseInt(this.baseView.id)).catch(err => err)
		},
		closeNav(e) {
			if (window.innerWidth < 960) {
				emit('toggle-navigation', {
					open: false,
				})
			}
		},
		async deleteMe() {
			const deleteId = this.baseView.id
			const activeViewId = this.activeView?.id

			const res = await this.$store.dispatch('removeTable', { tableId: this.baseView.tableId })
			if (res) {
				showSuccess(t('tables', 'Table "{emoji}{table}" removed.', { emoji: this.baseView.emoji ? this.baseView.emoji + ' ' : '', table: this.baseView.title }))

				// if the actual table was deleted, go to startpage
				if (deleteId === activeViewId) {
					await this.$router.push('/').catch(err => err)
				}
				this.showDeletionConfirmation = false
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
