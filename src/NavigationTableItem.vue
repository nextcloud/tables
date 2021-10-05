<template>
	<AppNavigationItem
		v-if="table"
		:title="table.title"
		:class="{active: activeTable && table.id === activeTable.id}"
		icon="icon-menu"
		@click="updateActiveTable(table.id)">
		<template slot="actions">
			<ActionButton
				icon="icon-fullscreen">
				{{ t('tables', 'Add view') }}
			</ActionButton>
			<ActionButton
				icon="icon-delete"
				@click="actionDelete">
				{{ t('tables', 'Delete table') }}
			</ActionButton>
		</template>
		<DialogConfirmation
			:description="t('tables', 'Do you really want to delete the table »{table}«?', { table: table.title })"
			:title="t('tables', 'Confirmation table deleting')"
			:cancel-title="t('tables', 'Cancel')"
			:confirm-title="t('tables', 'Delete')"
			confirm-class="error"
			:show-modal="showDeletionConfirmation"
			@confirm="deleteMe"
			@cancel="cancelDeletion" />
	</appnavigationitem>
</template>
<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showWarning } from '@nextcloud/dialogs'
import DialogConfirmation from './modals/DialogConfirmation'
import { mapGetters } from 'vuex'

export default {
	name: 'NavigationTableItem',
	components: {
		DialogConfirmation,
		ActionButton,
		AppNavigationItem,
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
	},
	methods: {
		cancelDeletion() {
			console.debug('click on cancel', null)
			this.showDeletionConfirmation = false
		},
		actionDelete() {
			this.showDeletionConfirmation = true
		},
		async deleteMe() {
			console.debug('click on confirm to delete', null)
			try {
				const response = await axios.delete(generateUrl('/apps/tables/table/' + this.table.id))
				console.debug('table deleted', response)
				showWarning(t('tables', 'Table "{table}" deleted.', { table: response.data.title }))
				if (this.table.id === this.activeTable.id) {
					// eslint-disable-next-line vue/custom-event-name-casing
					await this.$emit('activeTableWasDeleted', null)
				}
				// eslint-disable-next-line vue/custom-event-name-casing
				this.$emit('reloadNecessary')
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not fetch tables'))
			}
		},
		updateActiveTable(tableId) {
			this.$store.commit('setActiveTableId', tableId)
		},
	},
}
</script>
