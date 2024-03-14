<template>
	<NcModal v-if="showModal"
		size="normal"
		@close="actionCancel">
		<div class="modal__content" data-cy="transferTableModal">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Transfer table') }}</h2>
				</div>
			</div>
			<div class="row">
				<h3>{{ t('tables', 'Transfer this table to another user') }}</h3>
				<NcUserAndGroupPicker :select-users="true" :select-groups="false" :new-owner-user-id.sync="newOwnerUserId" />
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<NcButton type="warning" :disabled="newOwnerUserId === ''" data-cy="transferTableButton" @click="transferMe">
						{{ t('tables', 'Transfer') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton } from '@nextcloud/vue'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import NcUserAndGroupPicker from '../../shared/components/ncUserAndGroupPicker/NcUserAndGroupPicker.vue'
import { mapGetters } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'TransferTable',
	components: {
		NcModal,
		NcButton,
		NcUserAndGroupPicker,
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
			newOwnerUserId: '',
		}
	},
	computed: {
		...mapGetters(['getTable', 'activeTable']),
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
		actionCancel() {
			this.$emit('close')
		},
		async transferMe() {
			const transferId = this.table.id
			let activeTableId
			if (this.activeTable) {
				activeTableId = !this.isView ? this.activeTable.id : null
			}
			const res = await this.$store.dispatch('transferTable', { id: this.table.id, data: { newOwnerUserId: this.newOwnerUserId } })
			if (res) {
				showSuccess(t('tables', 'Table "{emoji}{table}" transfered to {user}', { emoji: this.table?.emoji ? this.table?.emoji + ' ' : '', table: this.table?.title, user: this.newOwnerUserId }))

				if (transferId === activeTableId) {
					await this.$router.push('/').catch(err => err)
				}

				this.actionCancel()
			}

		},
	},
}
</script>
