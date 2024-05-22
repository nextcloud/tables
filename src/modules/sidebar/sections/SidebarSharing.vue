<template>
	<div v-if="activeElement" class="sharing">
		<div v-if="canShareElement(activeElement)">
			<ShareForm :shares="shares" @add="addShare" @update="updateShare" />
			<ShareList :shares="shares" @remove="removeShare" @update="updateShare" />
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import shareAPI from '../mixins/shareAPI.js'
import ShareForm from '../partials/ShareForm.vue'
import ShareList from '../partials/ShareList.vue'
import { getCurrentUser } from '@nextcloud/auth'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	components: {
		ShareForm,
		ShareList,
	},

	mixins: [shareAPI, permissionsMixin],

	data() {
		return {
			loading: false,

			// shared with
			shares: [],
		}
	},

	computed: {
		...mapGetters(['activeElement', 'isView']),
	},

	watch: {
		activeElement() {
			if (this.activeElement) {
				this.loadSharesFromBE()
			}
		},
	},

	mounted() {
		if (this.activeElement) {
			this.loadSharesFromBE()
		}
	},

	methods: {
		getCurrentUser,
		async loadSharesFromBE() {
			this.loading = true
			this.shares = await this.getSharedWithFromBE()
			this.loading = false
		},
		async removeShare(share) {
			await this.removeShareFromBE(share.id)
			await this.loadSharesFromBE()
			// If no share is left, remove shared indication
			if (this.isView) {
				if (this.shares.find(share => ((share.nodeType === 'view' && share.nodeId === this.activeElement.id) || (share.nodeType === 'table' && share.nodeId === this.activeElement.tableId))) === undefined) {
					await this.$store.dispatch('setViewHasShares', { viewId: this.activeElement.id, hasShares: false })
				}
			} else {
				if (this.shares.find(share => (share.nodeType === 'table' && share.nodeId === this.activeElement.id)) === undefined) {
					await this.$store.dispatch('setTableHasShares', { tableId: this.activeElement.id, hasShares: false })
				}
			}
		},
		async addShare(share) {
			await this.sendNewShareToBE(share)
			await this.loadSharesFromBE()
		},
		async updateShare(data) {
			const shareId = data.id
			delete data.id
			await this.updateShareToBE(shareId, data)
			await this.loadSharesFromBE()
		},
	},
}
</script>
