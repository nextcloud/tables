<template>
	<NcModal v-if="showModal"
		size="normal"
		@close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Transfer table') }}</h2>
				</div>
			</div>
			<div class="row">
				<TransferForm :newOwnerUserId="newOwnerUserId" @add="addTransfer"/>
			</div>
			<div class="row">
				<div class="fix-col-4 space-T justify-between">
					<NcButton v-if="!prepareTransferTable" type="error" @click="prepareTransferTable = true">
						{{ t('tables', 'Transfer') }}
					</NcButton>
					<NcButton v-if="prepareTransferTable"
						:wide="true"
						type="error"
						@click="transferMe">
						{{ t('tables', 'I really want to transfer this table!') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton } from '@nextcloud/vue'
import TransferForm from '../sidebar/partials/TransferForm.vue'
import shareAPI from '../sidebar/mixins/shareAPI.js'
import { showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'TransferTable',
	components: {
		NcModal,
		NcButton,
		TransferForm,
	},
	mixins: [shareAPI, permissionsMixin],
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
			prepareTransferTable: false,
			loading: false,
			newOwnerUserId: '',
		}
	},
	computed: {
		...mapGetters(['activeElement', 'isView']),
		getTranslatedDescription() {
			return t('tables', 'Do you really want to transfer the table "{table}"?', { table: this.table?.title })
		},
	},
	methods: {
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		reset() {
			this.prepareDeleteTable = false
		},
		async transferMe() {
			console.info(this.table)
			console.info(getCurrentUser().uid)
			const transferId = this.table.id
			let activeTableId
			if (this.activeElement){
				activeTableId = this.isView ? this.activeElement.id : this.activeElement.tableId
				this.prepareTransferTable = false
			} 
			const res = await this.$store.dispatch('transferTable', { tableId: this.table.id, newOwnerUserId: 'test',  userId: getCurrentUser().uid})
			if (res) {
				showSuccess(t('tables', 'Table "{emoji}{table}" transfered', { emoji: this.table?.icon ? this.table?.icon + ' ' : '', table: this.table?.title }))

				// if the actual table was transfered, go to startpage
				if (transferId === activeTableId) {
					await this.$router.push('/').catch(err => err)
				}

				this.actionCancel()
			}

		},
		async addTransfer(id) {
			this.newOwnerUserId = id
		},
	},
}
</script>
