<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="t('tables', 'Transfer table')"
		data-cy="transferTableModal"
		size="normal"
		@closing="actionCancel">
		<div class="modal__content">
			<div class="row">
				<h3>{{ t('tables', 'Transfer this table to another user') }}</h3>
				<NcUserPicker :select-users="true" :select-groups="false" :selected-user-id.sync="selectedUserId" />
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<NcButton type="warning" :disabled="selectedUserId === ''" data-cy="transferTableButton" @click="transferMe">
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
	name: 'TransferTable',
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
		table: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			loading: false,
			selectedUserId: '',
		}
	},
	computed: {
		...mapState(useTablesStore, ['getTable', 'activeTable']),
		localTable() {
			return this.getTable(this.table.id)
		},
		userId() {
			return getCurrentUser().uid
		},
	},
	watch: {
		tableId() {
			if (this.table.id) {
				const table = this.getTable(this.table.id)
				this.title = table.title
				this.icon = table.emoji
			}
		},
	},
	methods: {
		...mapActions(useTablesStore, ['transferTable']),
		actionCancel() {
			this.$emit('close')
		},
		async transferMe() {
			const transferId = this.table.id
			let activeTableId
			if (this.activeTable) {
				activeTableId = !this.isView ? this.activeTable.id : null
			}

			const res = await this.transferTable({
				id: this.table.id,
				data: { newOwnerUserId: this.selectedUserId },
			})

			if (res) {
				showSuccess(t('tables', 'Table "{emoji}{table}" transferred to {user}', { emoji: this.table?.emoji ? this.table?.emoji + ' ' : '', table: this.table?.title, user: this.selectedUserId }))

				if (transferId === activeTableId) {
					await this.$router.push('/').catch(err => err)
				}

				this.actionCancel()
			}

		},
	},
}
</script>
