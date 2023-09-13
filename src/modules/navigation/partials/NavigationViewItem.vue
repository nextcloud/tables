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
		<template #counter>
			<NcCounterBubble v-if="canReadData(view)">
				{{ n('tables', '%n row', '%n rows', view.rowsCount, {}) }}
			</NcCounterBubble>
			<NcActionButton v-if="view.hasShares" icon="icon-share" :class="{'margin-right': !(activeView && view.id === activeView.id)}" @click="actionShowShare" />
			<div v-if="view.isShared && view.ownership !== userId && !canManageTable(view) && showShareSender" class="margin-left">
				<NcAvatar :user="view.ownership" />
			</div>
		</template>
		<template #actions>
			<NcActionButton v-if="canManageElement(view)"
				:close-after-click="true"
				@click="editView">
				<template #icon>
					<PlaylistEdit :size="20" />
				</template>
				{{ t('tables', 'Edit view') }}
			</NcActionButton>
			<NcActionButton v-if="canManageTable(view)"
				:close-after-click="true"
				@click="cloneView">
				<template #icon>
					<PlaylistPlay :size="20" decorative />
				</template>
				{{ t('tables', 'Duplicate view') }}
			</NcActionButton>
			<NcActionButton v-if="canShareElement(view)"
				icon="icon-share"
				:close-after-click="true"
				@click="actionShowShare">
				{{ t('tables', 'Share') }}
			</NcActionButton>
			<NcActionButton v-if="canCreateRowInElement(view)"
				:close-after-click="true"
				@click="actionShowImport(view)">
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
					<Connection :size="20" />
				</template>
			</NcActionButton>
			<NcActionButton v-if="canManageElement(view)"
				icon="icon-delete"
				:close-after-click="true"
				@click="emit('tables:view:delete', view)">
				{{ t('tables', 'Delete view') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>
<script>
import { NcAppNavigationItem, NcActionButton, NcCounterBubble, NcAvatar } from '@nextcloud/vue'
import '@nextcloud/dialogs/dist/index.css'
import { getCurrentUser } from '@nextcloud/auth'
import { mapGetters } from 'vuex'
import { showError } from '@nextcloud/dialogs'
import Table from 'vue-material-design-icons/Table.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import Connection from 'vue-material-design-icons/Connection.vue'
import PlaylistPlay from 'vue-material-design-icons/PlaylistPlay.vue'
import { emit } from '@nextcloud/event-bus'
import PlaylistEdit from 'vue-material-design-icons/PlaylistEdit.vue'
import Import from 'vue-material-design-icons/Import.vue'

export default {
	name: 'NavigationViewItem',

	components: {
		PlaylistEdit,
		// eslint-disable-next-line vue/no-reserved-component-names
		Table,
		NcAppNavigationItem,
		NcCounterBubble,
		NcActionButton,
		Connection,
		NcAvatar,
		PlaylistPlay,
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
		// this is good if you show the share sender via the table and show this as children
		showShareSender: {
		      type: Boolean,
		      default: true,
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
		emit,
		async actionShowShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
			await this.$router.push('/view/' + parseInt(this.view.id)).catch(err => err)
		},
		async actionShowIntegration() {
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
			await this.$router.push('/view/' + parseInt(this.view.id)).catch(err => err)
		},
		async editView() {
			emit('tables:view:edit', { view: this.view, viewSetting: {} })
		},
		async actionShowImport(view) {
			emit('tables:modal:import', { element: view, isView: true })
		},
		async cloneView() {
			let data = {
				tableId: this.view.tableId,
				title: this.view.title + ' ' + t('tables', 'Copy'),
				emoji: this.view.emoji,
			}
			const newViewId = await this.$store.dispatch('insertNewView', { data })
			if (newViewId) {
				data = {
					data: {
						columns: JSON.stringify(this.view.columns),
						filter: JSON.stringify(this.view.filter),
						sort: JSON.stringify(this.view.sort),
					},
				}
				const res = await this.$store.dispatch('updateView', { id: newViewId, data })
				if (res) {
					await this.$router.push('/view/' + newViewId)
				} else {
					showError(t('tables', 'Could not configure new view'))
				}
			} else {
				showError(t('tables', 'Could not create new view'))
			}
		},
	},

}
</script>
