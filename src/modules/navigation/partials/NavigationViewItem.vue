<template>
	<NcAppNavigationItem v-if="view"
		:name="view.title"
		:class="{active: activeView && view.id === activeView.id}"
		:force-menu="true"
		:to="'/view/' + parseInt(view.id)">
		<template #icon>
			<template v-if="view.emoji">
				{{ view.emoji }}
			</template>
			<template v-else>
				<Table :size="20" />
			</template>
		</template>
		<template #extra />
		<template #counter>
			<NcCounterBubble>
				{{ n('tables', '%n row', '%n rows', view.rowsCount, {}) }}
			</NcCounterBubble>
			<NcActionButton v-if="view.hasShares" icon="icon-share" :class="{'margin-right': !(activeView && view.id === activeView.id)}" @click="actionShowShare" />
			<div v-if="view.isShared && view.ownership !== userId" class="margin-left">
				<NcAvatar :user="view.ownership" />
			</div>
		</template>
		<template #actions>
			<NcActionButton v-if="canManageElement(view)"
				icon="icon-rename"
				:close-after-click="true"
				@click="editView">
				{{ t('tables', 'Edit view') }}
			</NcActionButton>
			<NcActionButton v-if="canShareElement(view)"
				icon="icon-share"
				:close-after-click="true"
				@click="actionShowShare">
				{{ t('tables', 'Share') }}
			</NcActionButton>
			<!--TODO: <NcActionButton v-if="true"
				:close-after-click="true"
				@click="actionShowImport(table)">
				{{ t('tables', 'Import') }}
				<template #icon>
					<Import :size="20" />
				</template>
			</NcActionButton> -->
			<NcActionButton
				:close-after-click="true"
				@click="actionShowIntegration">
				{{ t('tables', 'Integration') }}
				<template #icon>
					<Creation :size="20" />
				</template>
			</NcActionButton>
			<NcActionButton v-if="canDeleteElement(view)"
				icon="icon-delete"
				:close-after-click="true"
				@click="showDeletionConfirmation = true">
				{{ t('tables', 'Delete view') }}
			</NcActionButton>
		</template>
		<DialogConfirmation :description="getTranslatedDescription"
			:title="t('tables', 'Confirm view deletion')"
			:cancel-title="t('tables', 'Cancel')"
			:confirm-title="t('tables', 'Delete')"
			confirm-class="error"
			:show-modal="showDeletionConfirmation"
			@confirm="deleteMe"
			@cancel="showDeletionConfirmation = false" />
	</NcAppNavigationItem>
</template>
<script>
import { NcAppNavigationItem, NcActionButton, NcCounterBubble, NcAvatar } from '@nextcloud/vue'
import '@nextcloud/dialogs/dist/index.css'
import { getCurrentUser } from '@nextcloud/auth'
import { mapGetters } from 'vuex'
import { showSuccess } from '@nextcloud/dialogs'
import Table from 'vue-material-design-icons/Table.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import DialogConfirmation from '../../../shared/modals/DialogConfirmation.vue'
import Creation from 'vue-material-design-icons/Creation.vue'
import Import from 'vue-material-design-icons/Import.vue'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'NavigationViewItem',

	components: {
		// eslint-disable-next-line vue/no-reserved-component-names
		Table,
		NcAppNavigationItem,
		DialogConfirmation,
		NcCounterBubble,
		NcActionButton,
		Creation,
		NcAvatar,
		Import,
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
		view: {
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
		...mapGetters(['activeView']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the view "{view}"?', { view: this.view.title })
		},
		userId() {
			return getCurrentUser().uid
		},
	},
	methods: {
		async actionShowShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
			await this.$router.push('/view/' + parseInt(this.view.id)).catch(err => err)
		},
		// async actionShowImport(table) {
		// 	emit('tables:modal:import', table)
		// },
		async actionShowIntegration() {
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
			await this.$router.push('/view/' + parseInt(this.view.id)).catch(err => err)
		},
		async deleteMe() {
			const viewId = this.view.id
			const activeViewId = this.activeView?.id
			const res = await this.$store.dispatch('removeView', { viewId: this.view.id })
			if (res) {
				showSuccess(t('tables', 'View "{emoji}{view}" removed.', { emoji: this.view.emoji ? this.view.emoji + ' ' : '', view: this.view.title }))

				// if the actual view was deleted, go to startpage
				if (viewId === activeViewId) {
					await this.$router.push('/').catch(err => err)
				}
				this.showDeletionConfirmation = false
			}
		},
		editView() {
			emit('edit-view', this.view)
		},
	},

}
</script>
<style lang="scss">

// .app-navigation-entry__counter-wrapper {
// 	button.action-button {
// 		padding-right: 0;
// 	}
// 	margin-right: 0 !important;
// }

// .app-navigation-entry {
// 	.margin-right {
// 		margin-right: 44px;
// 	}
// 	.margin-left {
// 		margin-left: calc(var(--default-grid-baseline) * 2);
// 	}
// }

// .app-navigation-entry:hover {
// 	.margin-right {
// 		margin-right: 0;
// 	}

// 	.app-navigation-entry__counter-wrapper {
// 		display: inline-flex;
// 	}
// }

</style>
