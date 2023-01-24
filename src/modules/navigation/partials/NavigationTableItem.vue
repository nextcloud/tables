<template>
	<NcAppNavigationItem v-if="table"
		:title="table.title"
		:class="{active: activeTable && table.id === activeTable.id}"
		icon="icon-category-organization"
		:editable="canEditTableTitle"
		:edit-placeholder="t('tables', 'Tables title')"
		:edit-label="t('tables', 'Edit title')"
		:allow-collapse="false"
		:open="false"
		:force-menu="false"
		:to="'/table/' + parseInt(table.id)"
		@click="closeNav"
		@update:title="updateTableTitle">
		<template #actions>
			<NcActionButton v-if="!table.isShared"
				icon="icon-share"
				:close-after-click="true"
				@click="actionShowShare">
				{{ t('tables', 'Share') }}
			</NcActionButton>
			<NcActionButton v-if="!table.isShared"
				icon="icon-delete"
				:close-after-click="true"
				@click="actionDelete">
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
			@cancel="cancelDeletion" />
	</NcAppNavigationItem>
</template>
<script>
import { NcActionButton, NcAppNavigationItem } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import DialogConfirmation from '../../../shared/modals/DialogConfirmation.vue'
import { mapGetters } from 'vuex'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'NavigationTableItem',
	components: {
		DialogConfirmation,
		NcActionButton,
		NcAppNavigationItem,
	},
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
		canEditTableTitle() {
			if (!this.table.isShared) {
				return true
			}

			return !!this.table.onSharePermissions.manage
		},
	},
	methods: {
		async actionShowShare() {
			// this.$store.commit('setActiveTableId', this.table.id)
			await this.$router.push('/table/' + parseInt(this.table.id)).catch(err => err)
			emit('toggle-sidebar', { open: true, tab: 'sharing' })
		},
		closeNav(e) {
			if (window.innerWidth < 960) {
				emit('toggle-navigation', {
					open: false,
				})
			}
		},
		cancelDeletion() {
			// console.debug('click on cancel', null)
			this.showDeletionConfirmation = false
		},
		actionDelete() {
			this.showDeletionConfirmation = true
		},
		async deleteMe() {

			const res = await axios.delete(generateUrl('/apps/tables/table/' + this.table.id))
			if (res.status === 200) {
				showWarning(t('tables', 'Table "{table}" deleted.', { table: res.data.title }))
				await this.$store.dispatch('loadTablesFromBE')
			} else {
				showWarning(t('tables', 'Sorry, something went wrong.'))
				console.debug('axios error', res)
			}
			await this.$router.push('/').catch(err => err)

			this.showDeletionConfirmation = false
		},
		async updateTableTitle(newTitle) {
			// console.debug('try to set new table title: ', newTitle)
			try {
				// const data = { title: newTitle }
				const data = this.table
				data.title = newTitle
				// console.debug('data to update', data)
				const res = await axios.put(generateUrl('/apps/tables/table/' + this.table.id), data)
				if (res.status === 200) {
					showSuccess(t('tables', 'Table title is updated to "{table}"', { table: res.data.title }))
					await this.$store.dispatch('loadTablesFromBE')
				} else {
					showWarning(t('tables', 'Sorry, something went wrong.'))
					console.debug('axios error', res)
				}
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not update tables title'))
			}
		},
	},
}
</script>
