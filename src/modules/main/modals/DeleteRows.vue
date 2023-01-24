<template>
	<div>
		<DialogConfirmation :show-modal="rowsToDelete !== null"
			confirm-class="error"
			:title="n('tables', 'Delete row', 'Delete rows', rowsToDelete.length, {})"
			:description="n('tables', 'Are you sure you want to delete the selected row?', 'Are you sure you want to delete the %n selected rows?', rowsToDelete.length, {})"
			@confirm="deleteRows"
			@cancel="$emit('cancel')" />
	</div>
</template>

<script>

import DialogConfirmation from '../../../shared/modals/DialogConfirmation.vue'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'DeleteRows',
	components: {
		DialogConfirmation,
	},
	props: {
		rowsToDelete: {
			type: Array,
			default: null,
		},
	},
	methods: {
		deleteRows() {
			let error = false
			this.rowsToDelete.forEach(rowId => {
				const res = this.$store.dispatch('removeRow', { rowId })
				if (!res) {
					error = true
				}
			})
			if (error) {
				showError(t('tables', 'Error occurred while deleting rows.'))
			}
			this.$emit('cancel')
		},
	},
}
</script>
