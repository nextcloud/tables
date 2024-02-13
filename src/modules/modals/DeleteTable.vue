<template>
	<div>
		<DialogConfirmation :description="getTranslatedDescription"
			:title="t('tables', 'Confirm table deletion')"
			:cancel-title="t('tables', 'Cancel')"
			:confirm-title="t('tables', 'Delete')"
			confirm-class="error"
			:show-modal="showModal"
			@confirm="deleteMe"
			@cancel="$emit('cancel')" />
	</div>
</template>

<script>

import DialogConfirmation from '../../shared/modals/DialogConfirmation.vue'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { mapGetters } from 'vuex'

export default {
	components: {
		DialogConfirmation,
	},
	props: {
		table: {
			type: Object,
			default: null,
		},
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		...mapGetters(['activeElement', 'isView']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the table "{table}"? This will also delete all data, views and shares that are connected to this table.', { table: this.table?.title })
		},
	},
	methods: {
		async deleteMe() {
			const tableId = this.table.id
			let activeTableId
			if (this.activeElement) activeTableId = this.isView ? this.activeElement.id : this.activeElement.tableId
			const res = await this.$store.dispatch('removeTable', { tableId: this.table.id })
			if (res) {
				showSuccess(t('tables', 'Table "{emoji}{table}" removed.', { emoji: this.table.emoji ? this.table.emoji + ' ' : '', table: this.table.title }))

				// if the actual table was deleted, go to startpage
				if (tableId === activeTableId) {
					await this.$router.push('/').catch(err => err)
				}
				this.$emit('cancel')
			}
		},
	},
}
</script>
