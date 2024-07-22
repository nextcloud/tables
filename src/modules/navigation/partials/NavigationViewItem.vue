<template>
	<NcAppNavigationItem v-if="view"
		data-cy="navigationViewItem"
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
				<NcAvatar :user="view.ownership" :show-user-status="false" />
			</div>
		</template>
		<template #actions>
			<!-- EDIT -->
			<NcActionButton v-if="canManageElement(view)"
				:close-after-click="true"
				@click="editView">
				<template #icon>
					<PlaylistEdit :size="20" />
				</template>
				{{ t('tables', 'Edit view') }}
			</NcActionButton>

			<!-- DUPLICATE -->
			<NcActionButton v-if="canManageTable(view)"
				:close-after-click="true"
				@click="cloneView">
				<template #icon>
					<PlaylistPlay :size="20" decorative />
				</template>
				{{ t('tables', 'Duplicate view') }}
			</NcActionButton>

			<!-- SHARE -->
			<NcActionButton v-if="canShareElement(view)"
				icon="icon-share"
				:close-after-click="true"
				@click="actionShowShare">
				{{ t('tables', 'Share') }}
			</NcActionButton>

			<!-- IMPORT -->
			<NcActionButton v-if="canCreateRowInElement(view)"
				:close-after-click="true"
				@click="actionShowImport(view)">
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
			<NcActionButton v-if="!view.favorite"
				:close-after-click="true"
				@click="toggleFavoriteView(true)">
				{{ t('tables', 'Add to favorites') }}
				<template #icon>
					<Star :size="20" />
				</template>
			</NcActionButton>

			<!-- UNFAVORITE -->
			<NcActionButton v-if="view.favorite"
				:close-after-click="true"
				@click="toggleFavoriteView(false)">
				{{ t('tables', 'Remove from favorites') }}
				<template #icon>
					<StarOutline :size="20" />
				</template>
			</NcActionButton>

			<!-- DELETE -->
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
import '@nextcloud/dialogs/style.css'
import { getCurrentUser } from '@nextcloud/auth'
import { mapGetters } from 'vuex'
import { showError } from '@nextcloud/dialogs'
import Table from 'vue-material-design-icons/Table.vue'
import Star from 'vue-material-design-icons/Star.vue'
import StarOutline from 'vue-material-design-icons/StarOutline.vue'
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
		Star,
		StarOutline,
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
		async toggleFavoriteView(favorite) {
			if (favorite) {
				await this.$store.dispatch('favoriteView', {
					id: this.view.id,
				})
			} else {
				await this.$store.dispatch('removeFavoriteView', {
					id: this.view.id,
				})
			}
		},
	},

}
</script>
