<template>
	<div>
		<DialogConfirmation :show-modal="columnToDelete !== null"
			confirm-class="error"
			:title="t('tables', 'Delete column')"
			:description="deleteDescription"
			@confirm="deleteColumn"
			@cancel="$emit('cancel')" />
	</div>
</template>

<script>

import DialogConfirmation from '../../shared/modals/DialogConfirmation.vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'

export default {
	name: 'DeleteColumn',
	components: {
		DialogConfirmation,
	},
	props: {
		columnToDelete: {
			type: Object,
			default: null,
		},
		isView: {
			type: Boolean,
			default: false,
		},
		elementId: {
			type: Number,
			default: null,
		},
	},
	computed: {
		deleteDescription() {
			return t('tables', 'Are you sure you want to delete column "{column}"?', { column: this.columnToDelete.title })
		},
	},
	methods: {
		async deleteColumn() {
			const res = await this.$store.dispatch('removeColumn', { id: this.columnToDelete.id })
			if (!res) {
				showError(t('tables', 'Error occurred while deleting column "{column}".', { column: this.columnToDelete.title }))
			}
			await this.$store.dispatch('reloadViewsOfTable', { tableId: this.elementId })
			this.$emit('cancel')
		},
	},
}
</script>
