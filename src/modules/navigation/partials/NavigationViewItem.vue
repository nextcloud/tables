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
		<template #counter />
		<template #actions>
			<!-- TODO: Add permissions to buttons -->
			<NcActionButton
				icon="icon-rename"
				:close-after-click="true"
				@click="editView">
				{{ t('tables', 'Edit view') }}
			</NcActionButton>
			<!-- <NcActionButton
				icon="icon-share"
				:close-after-click="true"
				@click="actionShowShare">
				{{ t('tables', 'Share') }}
			</NcActionButton>
			<NcActionButton v-if="true"
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
			</NcActionButton> -->
			<NcActionButton
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
import { NcAppNavigationItem, NcActionButton } from '@nextcloud/vue'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import { showSuccess } from '@nextcloud/dialogs'
import Table from 'vue-material-design-icons/Table.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import DialogConfirmation from '../../../shared/modals/DialogConfirmation.vue'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'NavigationViewItem',

	components: {
		// eslint-disable-next-line vue/no-reserved-component-names
		Table,
		NcAppNavigationItem,
		DialogConfirmation,
		NcActionButton,
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
	},
	methods: {
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
