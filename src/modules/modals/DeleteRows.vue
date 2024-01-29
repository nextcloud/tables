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

import DialogConfirmation from '../../shared/modals/DialogConfirmation.vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { emit } from '@nextcloud/event-bus'
import { mapGetters } from 'vuex'

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
	computed: {
		...mapGetters(['activeElement', 'isView']),
	},
	methods: {
		deleteRows() {
			let error = false
			this.rowsToDelete.forEach(rowId => {
				const res = this.$store.dispatch('removeRow', {
					rowId,
					viewId: this.isView ? this.activeElement.id : null,
				})
				if (!res) {
					error = true
				}
			})
			if (error) {
				showError(t('tables', 'Error occurred while deleting rows.'))
			}
			emit('tables:selected-rows:deselect', this.activeElement.id)
			this.$emit('cancel')
		},
	},
}
</script>
