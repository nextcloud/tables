<template>
	<NcAppNavigationItem v-if="table"
		:name="table.title"
		:class="{active: activeTable && table.id === activeTable.id}"
		:allow-collapse="false"
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
			<div v-if="table.isShared" class="margin-left">
				<NcAvatar :user="table.ownership" />
			</div>
		</template>

		<template #actions>
			<NcActionButton v-if="canManageTable(table)"
				icon="icon-rename"
				:close-after-click="true"
				@click="$emit('edit-table', table.id)">
				{{ t('tables', 'Edit table') }}
			</NcActionButton>
			<NcActionButton v-if="!table.isShared"
				icon="icon-share"
				:close-after-click="true"
				@click="actionShowShare">
				{{ t('tables', 'Share') }}
			</NcActionButton>
			<NcActionButton v-if="!table.isShared"
				icon="icon-delete"
				:close-after-click="true"
				@click="showDeletionConfirmation = true">
				{{ t('tables', 'Delete table') }}
			</NcActionButton>
		</template>
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
import { mapGetters } from 'vuex'
import { emit } from '@nextcloud/event-bus'
import Table from 'vue-material-design-icons/Table.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'NavigationTableItem',

	components: {
		// eslint-disable-next-line vue/no-reserved-component-names
		Table,
		DialogConfirmation,
		NcActionButton,
		NcAppNavigationItem,
		NcCounterBubble,
		NcAvatar,
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
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the table "{table}"?', { table: this.table.title })
		},
	},

	methods: {
		async actionShowShare() {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
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
