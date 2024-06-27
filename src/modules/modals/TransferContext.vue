<template>
	<NcModal v-if="showModal"
		size="normal"
		@close="actionCancel">
		<div class="modal__content" data-cy="transferContextModal">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Transfer application') }}</h2>
				</div>
			</div>
			<div class="row">
				<h3>{{ t('tables', 'Transfer this application to another user') }}</h3>
				<NcUserPicker :select-users="true" :select-groups="false" :selected-user-id.sync="newOwnerId" />
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<NcButton type="warning" :disabled="newOwnerId === ''" data-cy="transferTableButton" @click="transferContext">
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
import '@nextcloud/dialogs/dist/index.css'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import NcUserPicker from '../../shared/components/ncUserPicker/NcUserPicker.vue'
import { mapGetters, mapState } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'TransferContext',
	components: {
		NcModal,
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
		...mapGetters(['getContext']),
		...mapState(['activeContextId']),
		localContext() {
			return this.getContext(this.context.id)
		},
		userId() {
			return getCurrentUser().uid
		},
	},
	methods: {
		actionCancel() {
			this.$emit('close')
		},
		async transferContext() {
			const transferId = this.context.id
			const res = await this.$store.dispatch('transferContext', { id: this.context.id, data: { newOwnerId: this.newOwnerId } })
			if (res) {
				showSuccess(t('tables', 'Context "{name}" transferred to {user}', { name: this.context?.name, user: this.newOwnerId }))

				if (transferId === this.activeContextId) {
					await this.$router.push('/').catch(err => err)
				}

				this.actionCancel()
			}

		},
	},
}
</script>
