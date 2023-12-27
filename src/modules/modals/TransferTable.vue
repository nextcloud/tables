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
				<div class="col-4 mandatory space-T">
					{{ t('tables', 'Owner') }}
				</div>

				<!-- We don't need to show this, since the current user will only see this if they're the owner? -->
				<div v-if="localTable !== undefined" class="col-3 inline space-T-small">
					<NcUserBubble
						:margin="4"
						:size="30"
						:display-name="localTable.ownerDisplayName"
						:user="localTable.owner" />
				</div>
			</div>
			<div class="row">
				<NcUserAndGroupPicker :select-users="true" :select-groups="false" :new-owner-user-id.sync="newOwnerUserId" />
			</div>
			<div class="row">
				<div class="fix-col-4 space-T justify-between">
					<NcButton type="warning" :disabled="newOwnerUserId === ''" data-cy="transferTableButton" @click="transferMe">
						{{ t('tables', 'Transfer') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcUserBubble } from '@nextcloud/vue'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
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
		NcUserBubble,
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
			this.reset()
			this.$emit('close')
		},
		reset() {
		},
		async transferMe() {
			const transferId = this.table.id
			let activeTableId
			if (this.activeElement) {
				activeTableId = this.isView ? this.activeElement.id : this.activeElement.tableId
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
