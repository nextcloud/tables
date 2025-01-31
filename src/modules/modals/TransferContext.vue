<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="t('tables', 'Transfer application')"
		size="normal"
		data-cy="transferContextModal"
		@closing="actionCancel">
		<div class="modal__content">
			<div class="row">
				<h3>{{ t('tables', 'Transfer the application "{context}" to another user', { context: context.name }) }}</h3>
				<NcUserPicker :select-users="true" :select-groups="false" :selected-user-id.sync="newOwnerId" />
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<NcButton type="warning" :disabled="newOwnerId === ''" data-cy="transferContextButton" @click="transferMe">
						{{ t('tables', 'Transfer') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcDialog, NcButton } from '@nextcloud/vue'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import NcUserPicker from '../../shared/components/ncUserPicker/NcUserPicker.vue'
import { mapState, mapActions } from 'pinia'
import { getCurrentUser } from '@nextcloud/auth'
import { useTablesStore } from '../../store/store.js'

export default {
	name: 'TransferContext',
	components: {
		NcDialog,
		NcButton,
		NcUserPicker,
	},
	mixins: [permissionsMixin],
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		context: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			loading: false,
			newOwnerId: '',
		}
	},
	computed: {
		...mapState(useTablesStore, ['activeContextId', 'getContext']),
		localContext() {
			return this.getContext(this.context.id)
		},
		userId() {
			return getCurrentUser().uid
		},
	},
	methods: {
		...mapActions(useTablesStore, ['transferContext']),
		actionCancel() {
			this.$emit('close')
		},
		async transferMe() {
			const transferId = this.context.id
			const res = await this.transferContext({
				id: this.context.id,
				data: { newOwnerId: this.newOwnerId },
			})

			if (res) {
				showSuccess(t('tables', 'Context "{name}" transferred to {user}', {
					name: this.context?.name,
					user: this.newOwnerId,
				}))

				if (transferId === this.activeContextId) {
					await this.$router.push('/').catch(err => err)
				}

				this.actionCancel()
			}
		},
	},
}
</script>
