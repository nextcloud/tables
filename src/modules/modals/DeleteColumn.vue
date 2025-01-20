<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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
import { mapActions } from 'pinia'
import { useTablesStore } from '../../store/store.js'
import { useDataStore } from '../../store/data.js'

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
		...mapActions(useTablesStore, ['reloadViewsOfTable']),
		...mapActions(useDataStore, ['removeColumn']),
		async deleteColumn() {
			const res = await this.removeColumn({
				id: this.columnToDelete.id,
				isView: this.isView,
				elementId: this.elementId,
			})

			if (!res) {
				showError(t('tables', 'Error occurred while deleting column "{column}".', { column: this.columnToDelete.title }))
			}
			if (!this.isView) {
				await this.reloadViewsOfTable({ tableId: this.elementId })
			}
			this.$emit('cancel')
		},
	},
}
</script>
