<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<DialogConfirmation :description="getTranslatedDescription"
			:title="t('tables', 'Confirm application deletion')"
			:cancel-title="t('tables', 'Cancel')"
			:confirm-title="t('tables', 'Delete')"
			confirm-class="error"
			:show-modal="showModal"
			data-cy="deleteContextModal"
			@confirm="deleteContext" @cancel="$emit('cancel')" />
	</div>
</template>

<script>

import DialogConfirmation from '../../shared/modals/DialogConfirmation.vue'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { mapState, mapActions } from 'pinia'
import { useTablesStore } from '../../store/store.js'

export default {
	components: {
		DialogConfirmation,
	},
	props: {
		context: {
			type: Object,
			default: null,
		},
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		...mapState(useTablesStore, ['activeContextId']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to delete the application "{context}"? This will also delete the shares and unshare the resources that are connected to this application.', { context: this.context?.name })
		},
	},
	methods: {
		...mapActions(useTablesStore, ['removeContext']),
		async deleteContext() {
			const res = await this.removeContext({ context: this.context, receivers: this.context.sharing })
			if (res) {
				showSuccess(t('tables', 'Application "{context}" removed.', { context: this.context.name }))
				// if the active context was deleted, go to startpage
				if (this.context.id === this.activeContextId) {
					await this.$router.push('/').catch(err => err)
				}
				this.$emit('cancel')
			}
		},
	},
}
</script>
